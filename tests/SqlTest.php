<?php

global $DB;

$sql = "SELECT i.id AS itemid, i.checklist, cl.teacheredit, ck.*, cl.course
FROM {checklist_item} i
JOIN {checklist} cl ON cl.id = i.checklist
LEFT JOIN {checklist_check} ck ON ck.item = i.id AND ck.userid = :userid
WHERE cl.autoupdate > 0 AND i.linkcourseid = :courseid AND i.itemoptional < :heading";
$params = ['userid' => $userid, 'courseid' => $courseid, 'heading' => 2];
$itemchecks = $DB->get_records_sql($sql, $params);
if (!$itemchecks) {
return;
}

//test static strings - Expr_Variable
$DB->get_records_sql("SELECT ...test... staticstring", $params);

//test simple variable - Scalar_String
$secretsql = "SELECT ...test... from var: sql2";
$DB->get_records_sql($secretsql, $params);

//test simple concat - Expr_Variable
$sqlConcat = 'SELECT i.id, c.usertimestamp, c.teachermark, c.teachertimestamp, c.teacherid
FROM {checklist_item} i
LEFT JOIN {checklist_check} c ';
$sqlConcat .= 'ON (i.id = c.item AND c.userid = ?) WHERE i.checklist = ? ';
$DB->get_records_sql($sqlConcat, $params);

//test inline concat - Expr_BinaryOp_Concat
//unknown type of $fields
$usql = "AND USERS QUERY";
$fields = get_all_user_name_fields(true, 'u');
$orderby = "i.id DESC";
$DB->get_records_sql("SELECT u.id, $fields FROM {user} u WHERE u.id ".$usql.' ORDER BY '.$orderby, $params);

//test var in string - Scalar Encapsed
//unknown type of $groupingsql
$groupingsql = checklist_class::get_grouping_sql(1, 2, 'i.');
$dateitems = $DB->get_records_sql("SELECT i.* FROM {checklist_item} i
JOIN {checklist_check} c ON c.item = i.id
WHERE i.checklist = ? AND i.duetime > 0 AND c.userid = ? AND usertimestamp = 0
AND $groupingsql
ORDER BY i.duetime", array(1, 2));

/*
Tests from Tomasz
"static string"
"also a" . " static string"
"dynamic " . $string
"also $dynamic string"
'and this is $static of course'
*/
$string = 'some string';
$stringinstring = "String in $string";
$DB->get_records_sql( "static string",$params);
$DB->get_records_sql( "also a" . " static string",$params);
$DB->get_records_sql( "dynamic " . $string ,$params);
$DB->get_records_sql( "also dynamic $string",$params);
$DB->get_records_sql( 'and this is a $static of course',$params);
$DB->get_records_sql( 'one ' . 'two ' . 'three',$params);
$DB->get_records_sql( $stringinstring ,$params);

//variable with variable and text
$statuses = ['todo', 'open', 'inprogress', 'intesting'];
list($insql, $inparams) = $DB->get_in_or_equal($statuses);
$sql5 = "SELECT * FROM {bugtracker_issues} WHERE status $insql";
$bugs = $DB->get_records_sql($sql5, $inparams);

// Scalar_Encapsed with unknown type of $insql
$bugs = $DB->get_records_sql("SELECT * FROM {bugtracker_issues} WHERE status $insql", $inparams);

// Scalar_Encapsed with safe variable from sql_fullname
$fullname = $DB->sql_fullname($first='firstname', $last='lastname');
$records = $DB->get_records_sql("SELECT * FROM {users} WHERE fullname= $fullname");


