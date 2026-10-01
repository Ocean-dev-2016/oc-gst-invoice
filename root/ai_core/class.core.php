<?php
/*
	File = class.core.php
	Date = 22-2-2018
*/

// include require files
require_once("class.functions.php");
require_once("class.db.php");

$is_admin = isset($is_admin) ? $is_admin : 0;
if( $is_admin == 1 )
	$ai_core->aiCheckLogin();

?>