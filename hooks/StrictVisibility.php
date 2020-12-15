<?php

declare(strict_types=1);

namespace Orklah\PsalmStrictVisibility\Hooks;

use Matrix\Exception;
use PhpParser\Node\Scalar\Encapsed;
use PhpParser\Node\Scalar\EncapsedStringPart;
use PhpParser\Node\Scalar\String_;
use Psalm\Internal\Analyzer\Statements\Expression\Call;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Identifier;
use Psalm\Codebase;
use Psalm\CodeLocation;
use Psalm\Context;
use Psalm\FileManipulation;
use Psalm\Internal\Analyzer\ClassLikeAnalyzer;
use Psalm\Internal\Codebase\Analyzer;
use Psalm\Internal\MethodIdentifier;
use Psalm\Internal\Type\TypeAlias\ClassTypeAlias;
use Psalm\Issue\PluginIssue;
use Psalm\IssueBuffer;
use Psalm\Plugin\Hook\AfterMethodCallAnalysisInterface;
use Psalm\StatementsSource;
use Psalm\Type\Atomic\TLiteralString;
use Psalm\Type\Union;
use function Psalm\getArguments;

/**
 * Prevents any assignment to a float value.
 */
class StrictVisibility implements AfterMethodCallAnalysisInterface
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
                    echo "\n-------------------------------------------------------\n";
                    echo '$'.$expr->var->name."->".$method_id->method_name."(...)\n";

                    $manager = new StrictVisibilityManager();


                    if ($method_storage->params[0]->type->hasType('string')){ //every has 0 param string (probably definition of method)

                        //Type of first param
                        $typeofarg1 = $expr->args[0]->value->getType();
                        echo "\nInfo: First argument type - $typeofarg1\n";

                        if ($typeofarg1 == "Expr_Variable"){
                            $name = $expr->args[0]->value->name;
                            $manager->getVariable($name, $context);
                        }
                        else if ($typeofarg1 == "Scalar_String") {
                            echo $expr->args[0]->value->value."\n";
                        }
                        else if ($typeofarg1 == "Expr_BinaryOp_Concat") {

                            $stack = [];
                            $manager->checkConcat($expr->args[0]->value, $stack);
                            //echo print_r($stack)."\n";

                            foreach ($stack as $item){
                                if ($item instanceof String_){
                                    echo $item->value;
                                }
                                else if ($item instanceof Variable){
                                    $manager->getVariable($item->name, $context);
                                }
                                else if ($item instanceof Encapsed){
                                    $manager->getEncapsedString($item->parts, $context);
                                }
                            }
                        }
                        else if ($typeofarg1 == "Scalar_Encapsed") {
                            //echo print_r($expr->args[0]->value);
                            $manager->getEncapsedString($expr->args[0]->value->parts, $context);
                        }
                        else {
                            echo "\nWarning: Unknown type!\n";
                        }
                    }
                    else {
                        echo "\nError: Definition of first param is not a string\n";
                    }
                }
            }

            /* $method_storage = $codebase->methods->getStorage($method_id);

            $is_private = $method_storage->visibility === ClassLikeAnalyzer::VISIBILITY_PRIVATE;
            $is_protected = $method_storage->visibility === ClassLikeAnalyzer::VISIBILITY_PROTECTED;
            if ($is_private || $is_protected) {
                //method is private or protected, check if the call was made on $this
                if ($expr->var instanceof Variable && $expr->var->name !== 'this') {
                    if ($is_private) {
                        $issue = new PrivateStrictVisibility(
                            'Calling private method ' . $method_storage->cased_name . ' via proxy',
                            new CodeLocation($statements_source, $expr->name)
                        );
                    } else {
                        $issue = new ProtectedStrictVisibility(
                            'Calling protected method ' . $method_storage->cased_name . ' via proxy',
                            new CodeLocation($statements_source, $expr->name)
                        );
                    }

                    IssueBuffer::accepts($issue, $statements_source->getSuppressedIssues());
                }
            }*/
        } catch (\Exception $e) {
            // can throw if storage is missing
        }
    }



}



class StrictVisibilityManager{

    public function checkConcat($child, &$stack){
        if ( $child instanceof Expr\BinaryOp\Concat ){
            self::checkConcat($child->left, $stack);
            self::checkConcat($child->right, $stack);
        }
        else {
            array_push($stack, $child);
        }
    }

    public function getVariable($name, $context){
        $name = '$'.$name;
        if ($context->hasVariable($name)) {
            if ($context->vars_in_scope[$name]->hasLiteralValue())
                echo $context->vars_in_scope[$name]->getSingleStringLiteral()->value;
            else {
                echo "\nWarning: Unknown type of variable $name\n";
                //echo print_r($context->vars_in_scope[$name])."\n";
            }
        }
        else{
            echo "\nError: $name variable not found in context!\n";
        }
    }

    public function getEncapsedString($parts, $context){
        foreach ($parts as $part){
            if ($part instanceof EncapsedStringPart){
                echo $part->value;
            }
            else if ($part instanceof Variable){
                self::getVariable($part->name, $context);
            }
            else {
                echo "\nWarning: Unknown part of Encapsed\n";
            }
        }
    }
}



class ProtectedStrictVisibility extends PluginIssue
{
}

class PrivateStrictVisibility extends PluginIssue
{
}
