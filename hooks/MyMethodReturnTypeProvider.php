<?php

namespace Klebann\MoodlePsalmPlugin\Hooks;

use Psalm\CodeLocation;
use Psalm\Context;
use Psalm\StatementsSource;
use Psalm\Type;
use Psalm\Type\Atomic\TKeyedArray;
use Psalm\Type\Atomic\TMixed;
use Psalm\Type\Atomic\TString;
use Psalm\Type\Union;
use Psalm\Plugin\Hook\MethodReturnTypeProviderInterface;


class TMoodleSqlCriteriaArray extends TKeyedArray{

}

class TMoodleSqlCriteriaString extends TString{
}

final class MyMethodReturnTypeProvider implements MethodReturnTypeProviderInterface
{
    private static $safe_methods = array(
        'sql_bitand', 'sql_bitnot', 'sql_bitor', 'sql_bitxor', 'sql_null_from_clause', 'sql_ceil', 'sql_equal',
        'sql_like', 'sql_like_escape', 'sql_length', 'sql_modulo', 'sql_position', 'sql_substr', 'sql_cast_char2int',
        'sql_cast_char2real', 'sql_compare_text', 'sql_order_by_text', 'sql_concat', 'sql_concat_join', 'sql_fullname',
        'sql_isempty', 'sql_isnotempty', 'sql_regex', 'sql_intersect'
    );

    private static $safe_methods_array = array('get_in_or_equal');

    /**
     * @return array<string>
     */
    public static function getClassLikeNames(): array
    {
        return [\moodle_database::class];
    }

    /**
     * @param array<Arg> $call_args
     * @param ?array<Union> $template_type_parameters
     * @return ?Union
     */
    public static function getMethodReturnType(StatementsSource $source, string $fq_classlike_name, string $method_name_lowercase, array $call_args, Context $context, CodeLocation $code_location, array $template_type_parameters = null, string $called_fq_classlike_name = null, string $called_method_name_lowercase = null) : Union
    {
        if (in_array($method_name_lowercase, self::$safe_methods_array)){
            $moodleSql = new TMoodleSqlCriteriaString();
            $moodleSql->text = $fq_classlike_name.'::'.$method_name_lowercase;

            $moodleSqlArray = new TMoodleSqlCriteriaArray([
                new Union([$moodleSql])
            ]);
            return new Union([$moodleSqlArray]);
        }
        else if (in_array($method_name_lowercase, self::$safe_methods)){
            $moodleSql = new TMoodleSqlCriteriaString();
            $moodleSql->text = $fq_classlike_name.'::'.$method_name_lowercase;
            return new Union([$moodleSql]);
        }
        else{
            return new Union([new TMixed()]);
        }
    }
}
