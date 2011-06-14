<?php

/**
 *
 * Copyright (C) 2003-2011  Ramon Antonio Parada <rap@ramonantonio.net>
 *
 * Database class
 *
 * @category	Database
 * @package		MyActiveRecord
 * @author		Ramon Antonio Parada <ramon@bigpress.net>
 * @copyright	2011 Ramon Antonio Parada
 * @version		0.5
 */


class Database {
	var $link;
	var $count = 0;
	var $record;
	var $result;
	var $lasttime = 0;
	var $totaltime = 0;

	//anhadir el soporte para estas dos
	var $debug = false;
	//var $profiling = false;


	/**
	 * Private function to create a new Database object
	 */
	function Database() {

		$this->link = @mysql_connect(SQL_HOST, SQL_LOGIN, SQL_PASSE);

		//  or Database::busy();
		if ($this->link) {
			if (!@mysql_select_db (SQL_DBASE)) {
				$this->link = FALSE;
			}
		}
		//or Database::busy();

	}

	function procesos() {
		$db =& Database::getInstance();
		$result = $db->query("show processlist;");
		$count = $db->num_rows($result);
		return $count;
	}


	function isbusy($limit = 5) {

		return  ((!$this->link) || ($this->procesos() > $limit));

	}


	function busy() {
		$error ="Estamos sufriendo una sobrecarga\n";
		$smarty =& Smarty::getInstance();

		$smarty->display("error.tpl");
		die ();
	}
	
	/**
	 * Retrieves an (unique) instance of the database handdler.
	 */
   	function &getInstance() {
		static $singleton;

		if (!$singleton)
			$singleton = new Database();

		return $singleton;
	}
	
	/**
	 * 
	 */
	function query($sql) {
		//if ($this->profiling){
		//	$mysql_query('set profiling=1', $this->link);
		//}

		$begin = microtime( true );
		$this->result = mysql_query($sql, $this->link);
		$end = microtime( true );
		$this->lasttime = $end - $begin;
		$this->totaltime += $this->lasttime;
		$this->count++;
		//$this->thresh = 10;
		//if( $this->thresh && ($end - $begin) >= $this->thresh ) error_log( ... );

		//if ($this->profiling){
		//	mysql_query("select sum(duration) as qtime from information_schema.profiling where query_id=1", $this->link);
	 	//}

		if (!$this->result) {
			 // trigger_error("MyActiveRecord::Query() - query failed: $strSQL with error: ".$db->error(), E_USER_WARNING);
			$this->sql_error($sql);
			return false;
		}

		return $this->result;

	}
	
	/**
	 * 
	 */
	function close () {
		mysql_close($this->link);
	}

	/**
	 * 
	 */
	function getCount() {
		return $this->count;
	}

	function found_rows() {

		$sql = "SELECT FOUND_ROWS();";
		$result = $this->query($sql);
		$item  = $this->fetch_array($result, MYSQL_NUM);
		return $item[0];
	}

	function fetch_array($query_id, $result_type= MYSQL_ASSOC ) {
		$this->record = mysql_fetch_array($query_id,$result_type);
		return $this->record;
	}


	function fetch_assoc($resource) {
		$this->record = mysql_fetch_assoc($resource);
		return $this->record;
	}


 	function fetch_object($query_id) {
	   // $this->record = mysql_fetch_object($query_id);
		return mysql_fetch_object($query_id);
	}

	function num_rows($query_id) {
		return ($query_id) ? mysql_num_rows($query_id) : 0;
	}

	function num_fields($query_id) {
		return ($query_id) ? mysql_num_fields($query_id) : 0;
	}


	function fetch_row($resource) {
		return mysql_fetch_row($resource  );

	}

	function free_result($query_id) {
		return mysql_free_result($query_id);
	}

	/**
	*@deprecated
	*/
	function real_escape_string($str) {
		return mysql_real_escape_string($str);

	}

	function affected_rows() {
		return mysql_affected_rows($this->link);
	}

	function insert_id() {
		return mysql_insert_id();
	}

	function error() {
		return $this->link?mysql_error($this->link):false;
	}

	/**
		@deprecated
	*/
	function close_db() {
		if($this->link) {
			return mysql_close($this->link);
		} else {
			return false;
		}
	}


	function escape_string($str) {

		return mysql_real_escape_string($str, $this->link);

	}


	function sql_error($sql) {

		$description = mysql_error();
		$number = mysql_errno();
		if ($number == "0") {

			Database::busy();
		}
		$message = "Query Error";
		$error ="MySQL Error : $message\n";
		$error .="User : ".SQL_LOGIN."\n";
		$error.="Error Number: $number $description\n";
		$error.="SQL		 : $sql\n";
		$error.="Date		: ".date("D, F j, Y H:i:s")."\n";
		$error.="IP		  : ".getenv("REMOTE_ADDR")."\n";
		$error.="Browser	 : ".getenv("HTTP_USER_AGENT")."\n";
		$error.="Referer	 : ".getenv("HTTP_REFERER")."\n";
		$error.="PHP Version : ".PHP_VERSION."\n";
		$error.="OS		  : ".PHP_OS."\n";
		$error.="Server	  : ".getenv("SERVER_SOFTWARE")."\n";
		$error.="Server Name : ".getenv("SERVER_NAME")."\n";
		$error.="Script Name : ".getenv("SCRIPT_NAME")."\n";
		echo($message);
	   echo($error);
		//exit();
	}

}


