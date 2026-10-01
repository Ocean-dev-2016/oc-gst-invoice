<?php
/*
	File = class.db.php
	Date = 18-5-2015
*/

class AI_Db
{
	// define variable
	private $db_host = '';
	private $db_user = '';
	private $db_pass = '';
	private $db_dbname = '';
	private $conn = '';
	
	public function __construct( $host = '', $user = '', $pass = '', $db = '')
	{
		global $ai_core;
		
		$this->db_host = $host;
		$this->db_user = $user;
		$this->db_pass = $pass;
		$this->db_dbname = $db;
		
		if($this->db_host == '' && $this->db_user == '' && $this->db_dbname == '')
			$ai_core->aiGetError("Database configuration not set.");
		
		//mysqli_connect($this->db_host, $this->db_user, $this->db_pass, $this->db_dbname) or die(mysqli_error($this->conn));
		
		if($this->conn = mysqli_connect($this->db_host, $this->db_user, $this->db_pass, $this->db_dbname))
		{
			mysqli_set_charset($this->conn,"utf8");
			return true;
		}
		else{
			$ai_core->aiGetError("Database not connected.");
		}
	}
	
	public function aiQuery( $qry = '' )
	{
		if($qry == '')
			return false;
		
		$res = mysqli_query( $this->conn, $qry ) or die(mysqli_error( $this->conn ));
		return $res;
	}
	
	public function aiFetchArray( $res = '' )
	{
		if($res == '')
			return false;
		
		$data = array();
		$data_cnt = 0;
		
		while( $row = mysqli_fetch_array( $res, MYSQLI_ASSOC ) )
		{
			$data[$data_cnt] = $row;
			$data_cnt++;
		}
		
		return $data;
	}
	
	public function aiFetchObject( $res = '' )
	{
		if($res == '')
			return false;
		
		$data = array();
		$data_cnt = 0;
		
		while( $row = mysqli_fetch_object( $res ) )
		{
			$data[$data_cnt] = $row;
			$data_cnt++;
		}
		
		return $data;
	}
	
	public function aiGetQuery( $qry = '' )
	{
		if($qry == '')
			return false;
		
		$res = $this->aiQuery( $qry );
		$data = $this->aiFetchArray( $res );
		
		return $data;
	}
	
	public function aiGetQueryObj( $qry = '' )
	{
		if($qry == '')
			return false;
		
		$res = $this->aiQuery( $qry );
		$data = $this->aiFetchObject( $res );
		
		return $data;
	}
	
	public function aiGetSetting($value){
		$res = $this->aiQuery("select * from ".DB_PREFIX."setting where name='".$value."'");
		$data = $this->aiFetchObject( $res );
		
		return $data[0]->value;
	}
	public function aiGetTable( $tbl = '' )
	{
		if($tbl == '')
			return false;
		
		$qry = "SELECT * FROM `".$tbl."`";
		$res = $this->aiQuery( $qry );
		$data = $this->aiFetchArray( $res );
		
		return $data;
	}
	
	public function aiLastInsert()
	{
		return mysqli_insert_id( $this->conn );
	}
}

$ai_db = new AI_Db(DB_HOST, DB_USER, DB_PASS, DB_DATABASE);
$ai_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_DATABASE);
?>