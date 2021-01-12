# moodle-psalm-plugin
Moodle Psalm-Plugin (MPP) is a [Psalm](https://github.com/vimeo/psalm) plugin, that finds all vulnerable to SQL injection $DB->methods and displays content of SQL query.

The script is just for finding and showing you code that you should review. 

## Installation:

```console
$ composer require --dev klebann/moodle-psalm-plugin
$ vendor/bin/psalm-plugin enable klebann/moodle-psalm-plugin
```

## Usage:

Copy [psalm-plugin.xml](psalm-plugin.xml) and [issueHandlers.xml](issueHandlers.xml) to Psalm root directory and modify it for your specific usuage.

Run your usual Psalm command:
```console
$ vendor/bin/psalm --config=psalm-plugin.xml --no-diff
```

## Explanation:

Created to automate Security-focused code review for moodle plugins:

"In order to prevent SQL injection, always use data placeholders in your queries (? or :named) to pass data from users into the queries." ~ [Data Manipulation API - Placeholders](https://docs.moodle.org/dev/Data_manipulation_API#Placeholders)

## Example:

PHP:
```php
if ($checklist->teacheredit == CHECKLIST_MARKING_STUDENT) {
    $date = ', MAX(c.usertimestamp) AS datesubmitted';
    $where = 'c.usertimestamp > 0';
} else {
    $date = ', MAX(c.teachertimestamp) AS dategraded';
    $where = 'c.teachermark = '.CHECKLIST_TEACHERMARK_YES;
}

$total = count($items);

list($usql, $uparams) = $DB->get_in_or_equal($users);
list($isql, $iparams) = $DB->get_in_or_equal(array_keys($items));

$namefields = get_all_user_name_fields(true, 'u');

$sql = 'SELECT u.id AS userid, (SUM(CASE WHEN '.$where.' THEN 1 ELSE 0 END) * ? / ? ) AS rawgrade'.$date;
$sql .= ' , '.$namefields;
$sql .= ' FROM {user} u LEFT JOIN {checklist_check} c ON u.id = c.userid';
$sql .= " WHERE u.id $usql";
$sql .= " AND c.item $isql";
$sql .= ' GROUP BY u.id, '.$namefields;

$params = array_merge($uparams, $iparams);
$params = array_merge(array($checklist->maxgrade, $total), $params);

$grades = $DB->get_records_sql($sql, $params);
```

Output (This example will be skiped in next release because is safe):
```bash
INFO: PossibleSqlInjection - ../../mod/checklist/lib.php:342:24 - Calling unsafe sql method $DB->get_records_sql
Description:
    Safe variable $namefields: created by get_all_user_name_fields()
    Safe variable $usql: created by get_in_or_equal()
    Safe variable $isql: created by get_in_or_equal()
SQL:
    SELECT u.id AS userid, (SUM(CASE WHEN $namefields([c.usertimestamp > 0][c.teachermark = CHECKLIST_TEACHERMARK_YES]) THEN 1 ELSE 0 END) * ? / ? ) AS rawgrade $date([, MAX(c.usertimestamp) AS datesubmitted][, MAX(c.teachertimestamp) AS dategraded]) , $namefields FROM {user} u LEFT JOIN {checklist_check} c ON u.id = c.userid WHERE u.id $usql AND c.item $isql GROUP BY u.id, $namefields
Documentation -
        $grades = $DB->get_records_sql($sql, $params);
```
