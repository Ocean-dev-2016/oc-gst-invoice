<?php
/*
	File = class.defaults.php
	Date = 18-5-2015
*/

class AI_Default
{
	// define variable
	public $def_set = array('test');
	
	function bcGetDefault( $tbl = '' )
	{
		if($tbl == '')
			return false;
		
		$this->def_set = $bc_db->bcGetTable( $tbl );
	}
}

$ai_func = new AI_Default();
?>