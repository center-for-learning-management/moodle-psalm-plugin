<?php

declare(strict_types=1);

namespace Klebann\MoodlePsalmPlugin\Hooks;

use PhpParser\Node\Scalar\Encapsed;
use PhpParser\Node\Scalar\EncapsedStringPart;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use Psalm\Codebase;
use Psalm\CodeLocation;
use Psalm\Context;
use Psalm\FileManipulation;
use Psalm\Internal\MethodIdentifier;
use Psalm\Issue\PluginIssue;
use Psalm\IssueBuffer;
use Psalm\Plugin\Hook\AfterMethodCallAnalysisInterface;
use Psalm\StatementsSource;
use Psalm\Type\Union;

$loc = 0;

/**
 * Finds and checks usage of $DB->get_records_sql()
 */
class MoodleScanner implements AfterMethodCallAnalysisInterface
{
    /**
     * @param MethodCall|StaticCall $expr
     * @param FileManipulation[]    $file_replacements
     */
    public static function afterMethodCallAnalysis(
        Expr $expr,
        string $method_id,
        string $appearing_method_id,
        string $declaring_method_id,
        Context $context,
        StatementsSource $statements_source,
        Codebase $codebase,
        array &$file_replacements = [],
        Union &$return_type_candidate = null
    ) : void {

        if (!$expr instanceof MethodCall) {
            return;
        }

        if (!$expr->name instanceof Identifier) {
            return;
        }

        try {
            $method_id = new MethodIdentifier(...explode('::', $declaring_method_id));
            $method_storage = $codebase->methods->getStorage($method_id);

            if ($method_id->fq_class_name == "moodle_database"){ //expr name DB
                if ($method_id->method_name == "get_records_sql"){ //add array with all sql methods
                    //echo "\n-------------------------------------------------------\n";
                    //echo '$'.$expr->var->name."->".$method_id->method_name."(...)\n";

                    //variables and objects
                    $manager = new MoodleScannerManager();
                    global $loc;
                    $loc = $expr->getLine();
                    $sql = "";
                    $info = "";

                    if ($method_storage->params[0]->type->hasType('string')){ //every has 0 param string (probably definition of method)

                        //Type of first param
                        $typeofarg1 = $expr->args[0]->value->getType();
                        //$info .= "    Info: Arg1 Type = $typeofarg1\n";

                        //$info .= print_r($statements_source, true);
                        $sql .= $manager->chooseAction($typeofarg1, $expr->args[0], $context, $statements_source, $info);

                        //echo "\nSQL: $sql\n";

                        $message = "Calling unsafe sql method ".'$DB->' . $method_storage->cased_name;

                        if (!empty($info)){
                            $message .= "\nDescription:\n$info";
                        }
                        else {
                            $message .= "\n";
                        }

                        $message .= "SQL:\n    $sql" . "\nDocumentation -";

                        $issue = new PossibleSqlInjection(
                            $message,
                            new CodeLocation($statements_source, $expr->name)
                        );

                        IssueBuffer::accepts($issue, $statements_source->getSuppressedIssues());
                    }
                    else {
                        echo "\nError: Definition of first param is not a string\n";
                    }
                }
            }
        } catch (\Exception $e) {
            // can throw if storage is missing
        }
    }
}


class MoodleScannerManager{

    public function chooseAction($type, $assign, $context, $statements_source, &$info){
        //TODO: differ code for 2 types (Expr\Assign and Node\Arg)
        //$info .= "$type\n";
        //$info .= print_r($assign, true);
        switch($type){
            case "Expr_Variable":
                if (isset($assign->expr)){
                    return $assign->expr->value;
                }
                else{
                    $name = $assign->value->name;
                    return self::getVariable($name, $context, $statements_source, $info);
                }
            case "Scalar_String":
                if (isset($assign->value->value)){
                    return $assign->value->value; //bezpieczna
                }
                else {
                    return $assign->expr->value;
                }
            case "Expr_BinaryOp_Concat":
                $stack = [];
                $sql = "";

                if (isset($assign->expr)){
                    $content = $assign->expr;
                }
                else {
                    $content = $assign->value;
                }

                self::parseConcat($content, $stack);
                //$info .= print_r($stack,true);

                foreach ($stack as $item){
                    //$info .= print_r($item, true);
                    if ($item instanceof String_){
                        $sql .= $item->value;
                    }
                    else if ($item instanceof Variable){
                        $sql .= self::getVariable($item->name, $context, $statements_source, $info);
                    }
                    else if ($item instanceof Encapsed){
                        $sql .= self::getEncapsedString($item->parts, $context, $statements_source, $info);
                    }
                    else if ($item instanceof Expr\ConstFetch){
                        $sql .= $item->name->getAttributes()['resolvedName'];
                    }
                    else{
                        $info .= "    Unknown type of item in $type :\n".print_r($item, true);
                    }

                }
                return $sql;
            case "Scalar_Encapsed":
                if (isset($assign->expr)){
                    $content = $assign->expr;
                }
                else{
                    $content = $assign->value;
                }
                $parts = $content->parts;
                return self::getEncapsedString($parts, $context, $statements_source, $info);
            case "Expr_StaticCall":
                $name = '$'.$assign->var->name;
                $info .= "    Unknown type of $name - $type\n";
                return " $name ";
            default:
                $info .= "    Warning: Unknown type ($type) of variable!\n";
                $info .= print_r($assign, true);
                return " $type ";
        }
    }

    /**
     * @param MethodCall|StaticCall $child
     * @param array $stack
     */
    public function parseConcat($child, array &$stack){
        if ( $child instanceof Expr\BinaryOp\Concat ){
            self::parseConcat($child->left, $stack);
            self::parseConcat($child->right, $stack);
        }
        else {
            array_push($stack, $child);
        }
    }

    /**
     * @param string $name
     * @param Context $context
     * @param StatementsSource $statements_source
     * @param string $info
     * @return string
     */
    public function getVariable(string $name, Context $context, StatementsSource $statements_source, string &$info){
        $namevar = '$'.$name;

        if ($context->hasVariable($namevar)) {
            $var_in_scope = $context->vars_in_scope[$namevar];

            if ($var_in_scope->hasLiteralValue()) {
                $literalstrings = $var_in_scope->getLiteralStrings();

                if (count($literalstrings) == 1){
                    try{
                        return $var_in_scope->getSingleStringLiteral()->value;
                    }
                    catch (\Exception $e){ //getsingleStringLiteral can throw if false
                        $info .= "    Exception: ".$e->getMessage();
                        return $namevar;
                    }
                }

                $sql = " $namevar(";
                foreach ($literalstrings as $literalstring){
                    $sql .= '['.$literalstring->value.']';
                } $sql .= ')';
                return $sql;
            }
            else if($var_in_scope->hasType("string")) {
                if ($var_in_scope->getAtomicTypes()['string'] instanceof TMoodleSqlCriteriaString) {
                    $nameoffunction = $var_in_scope->getAtomicTypes()['string']->text;
                    $info .= "    Safe variable $namevar: created by $nameoffunction()\n";
                    return $namevar;
                }
                else {
                    return self::findInStatementsSource($name, $statements_source, $context,$info);
                }
            }
            else {
                return self::unknownType($namevar, $context, $info);
            }
        }
        else{
            $info .= "    Warning: $namevar variable not found in context!\n";
            return $namevar;
        }
    }

    private function unknownType(string $name, Context $context, string &$info){
        $info .= "    Warning: Unknown type of $name\n";

        //$info .= print_r($context->vars_in_scope[$name]->getAtomicTypes(), true)."\n";
        return $name;
    }

    /**
     * @param array $parts
     * @param Context $context
     * @param StatementsSource $statements_source
     * @param string $info
     * @return string
     */
    public function getEncapsedString(array $parts, Context $context, StatementsSource $statements_source, string &$info){
        $sql = "";

        foreach ($parts as $part){
            if ($part instanceof EncapsedStringPart){
                $sql .= $part->value;
            }
            else if ($part instanceof Variable){
                $sql .= self::getVariable($part->name, $context, $statements_source, $info);
            }
            else {
                $info .= "    Warning: Unknown part of Encapsed\n";
            }
        }

        return $sql;
    }

    /**
     * @param string $name
     * @param StatementsSource $statements_source
     * @param Context $context
     * @param string $info
     * @return string
     */
    public function findInStatementsSource($name, $statements_source, $context, &$info){
        global $loc;
        $infotmp = "";

        if ($loc == 0){
            $infotmp .= "    Warning: LOC == 0!\n";
            return '$'.$name;
        }

        try{
            $array = (array) array_values((array)$statements_source)[0];
            $stmts = array_values($array)[0]->stmts;

            $assigns = [];
            self::findAssignsInArray($stmts, $assigns); //get all variables
            //$infotmp .= print_r($assigns, true);


            $sql = "";
            foreach($assigns as $assign){
                if (!isset($assign->var->name)){
                    continue;
                }

                if ($assign->var->name == $name){
                    $type = $assign->expr->getType();
                    //$infotmp .= print_r($assign, true);

                    /**
                     * Checking Line Of Code (LOC).
                     * $variableLOC should be smaller than LOC of get_records_sql.
                     * @var Variable $var
                     */
                    $var = $assign->var;
                    $variableLOC = $var->getLine();
                    if ($variableLOC > $loc){
                        break;
                    }

                    if($assign instanceof Expr\Assign) {
                        $infotmp = "";
                        $sql = self::chooseAction($type, $assign, $context, $statements_source, $infotmp);
                    }
                    else {
                        $sql .= self::chooseAction($type, $assign, $context, $statements_source, $infotmp);
                    }
                }
            }

            $info .= $infotmp;
            if ($sql == ""){
                return self::unknownType('$'.$name, $context, $infotmp);
            }
            else{
                return $sql;
            }
        }
        catch  (\Exception $e) { //can throws when not find ->expr->var->name
            $info .= "    Exception in findInStatementsSource\n";
            $info .= "    ".$e->getMessage().$e->getTraceAsString()."\n";
            return self::unknownType('$'.$name, $context, $info);
        }
    }

    /**
     * @param array|object $array
     */
    public function findAssignsInArray($array, &$stack){
        foreach ($array as $item){
            if ($item instanceof Expr\Assign || $item instanceof Expr\AssignOp){
                array_push($stack, $item);
            }
            else{
                if (is_array($item) || is_object($item)) {
                    self::findAssignsInArray($item, $stack);
                }
            }
        }
    }

}


class PossibleSqlInjection extends PluginIssue
{
    public const ERROR_LEVEL = -1;
    public const SHORTCODE = 0;
}
