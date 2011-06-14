<?php


/**
 * MyActiveRecord
 *
 * A simple, speedy Object/Relational Mapper for MySQL based on Martin 
 * Fowler's ActiveRecord pattern and heavily influenced by the implementation 
 * of the same name in Ruby on Rails.
 *
 * Features
 * 
 *	-	Mapping of table to class and row to object
 *	-	Relationship retrieval
 *	-	Data validation and error handling
 *	-	PHP5 and PHP4 compatibility
 *
 * Limitations
 * 
 * This class acheives simplicty of use and implementation through the 
 * following 'by-design' limitations:
 *
 *	1.	This class talks to MySQL only.
 *
 *	2.	Table/Class mapping is achieved by each database table being named 
 *		IDENTICALLY to the MyActiveRecord subclass that will represent it 
 *		(but in lowercase for compatibility reasons).
 *
 *	3.	Every database table mapped by MyActiveRecord MUST have an 
 *		autoincrementing primary-key named `id`.
 * 
 *
 *
 * @category	Database
 * @package		MyActiveRecord
 * @author		Ramon Antonio Parada <ramon@bigpress.net>
 * @author		Jake Grimley <jake.grimley@mademedia.co.uk>
 * @copyright	2006 Jake Grimley
 * @copyright	2011 Ramon Antonio Parada
 * @version		0.5
 */
class MyActiveRecord {


	/**
	 * A static method for preparing SQL queries. Safely escapes query paramaters
	 * using standard printf syntax, e.g.:
	 * <code>
	 * $sql = MyActiveRecord::Prepare("SELECT * FROM person WHERE last = '%s' AND age=%d", $_GET['lastname'], $_GET['age']);
	 * $people = MyActiveRecord::FindBySql('Person', $sql);
	 * </code>
	 *
	 * @static
	 * @param	string	sql	A raw sql statement containing formatting
	 * @param	mxd	Args	multiple arguments to be substituted into arguments. See sprintf documentation.
	 * @return	string	escaped sql string 
	*/
	function Prepare() {
		$args = func_get_args();
		$rawsql = array_shift($args);
		if( get_magic_quotes_gpc() ) $args = array_map( 'stripslashes', $args );
		$args = array_map( 'mysql_escape_string', $args );
		$sql = vsprintf($rawsql, $args);
		return $sql;
	}

	/**
	 * Execute a SQL Query using Connection()
	 *
	 * @static
	 * @param	string	strSQL	A SQL statement
	 * @return	resource	A MySQL result resource. False on failure.
	 */
	function Query($strSQL) {
		$db =& Database::getInstance();

		if( $rscResult = $db->query($strSQL) )
		// return result
		{
			return $rscResult;
		}
		else
		// failure
		{
			trigger_error("MyActiveRecord::Query() - query failed: $strSQL with error: ".$db->error(), E_USER_WARNING);
			return false;
		}
	}

	/**
	 * return a date formatted for the database
	 * @static
	 * @param	int	intTimeStamp	A unix timestamp
	 * @return	string	mysql format date string
	 */
	function DbDate($intTimeStamp=null)
	{
		return date('Y-m-d', $intTimeStamp ? $intTimeStamp:mktime() );
	}

	/**
	 * return a datetime formatted for the database
	 * @static
	 * @param	int	intTimeStamp	A unix timestamp
	 * @return	string	mysql format datetime string
	 */	
	function DbDateTime($intTimeStamp=null)
	{
		return date('Y-m-d H:i:s', $intTimeStamp ? $intTimeStamp:mktime() );
	}
	
	/**
	 * return a unix timestamp from a database field
	 * @static
	 * @param	string	mysql datetime
	 * @return	int	unix timestamp	
	 */
	function TimeStamp($strMySQLDate)
	{
		return strtotime($strMySQLDate);
	}

	/**
	* return an array containing names of tables in database
	* @static
	* @return	array 	names of tables in database
	*/
	function Tables()
	{
$db =& Database::getInstance();
		static $tables = array();
		if( !count($tables) )
		{
			$result = MyActiveRecord::Query('SHOW TABLES')
				or trigger_error('MyActiveRecord::Tables() - Cannot list tables', E_USER_ERROR);
			while( $row = $db->fetch_row($result) )
			{
				$tables[]=$row[0];
			}
		}
		return $tables;
	}
	
	/**
	* checks to see if strTable exists in database returning true or false
	* @static
	* @param	string	strTable	name of table to check for
	* @return	bool	true/false
	*/
	function TableExists($strTable)
	{
		return in_array( $strTable, MyActiveRecord::Tables() );
	}
	
	/**
	* gets table representing class in database
	* @static
	* @param	mixed	$mxd	either a string(class name) or an object
	* @return	string	name of table in database representing class(string) or object
	*			false if no table found
	*/
	function Class2Table($mxd) {
		$origClass = is_object($mxd) ? get_class($mxd) : $mxd;
		class_exists($origClass)
			or trigger_error("MyActiveRecord::Class2Table - Class $origClass does not exist", E_USER_ERROR);
		$class = $origClass;

		$existe = false;

		while( !$existe  && ($class !='MyActiveRecord') ) {

			$table = strtolower($class);
			$existe = MyActiveRecord::TableExists( strtolower($class));

			if (!$existe) {
				$table = strtolower(DOMAIN."_".$class);
				$existe = MyActiveRecord::TableExists( strtolower($class));
			}

			if (!$existe)
			$class = get_parent_class($class);
		}

		if($table=='myactiverecord') {
			trigger_error("MyActiveRecord::Class2Table - Class $origClass does not have a table representation", E_USER_ERROR);
			return false;
		}
		return $table;
	}
	
	/**
	 * Returns an array describing the specified table, or false if the table 
	 * does not exist in the database.
	 * The array contains one array per database column, keyed by the column 
	 * name. To see the structure of the array you could try: 
	 * <code>
	 * print_r( MyActiveRecord::Columns('your_table') );
	 * </code>
	 *
	 * @static
	 * @param	string	strTable	The name of the database table
	 * @return	array	Table columns. False if the table does not exist.
	 */
	function Columns($strTable)
	{
$db =& Database::getInstance();
		
		$strTable = MyActiveRecord::class2Table($strTable);
		
		// cache results locally
		static $cache=array();
		
		// already cached? return columns array
		if( isset($cache[$strTable]) )
		{
			return $cache[$strTable];
		}
		else
		// connect to database and run 'describe' query to get results
		{
			if( $rscResult = MyActiveRecord::Query("SHOW COLUMNS FROM $strTable") )
			{
				$arrFields = array();
				while( $col = $db->fetch_assoc($rscResult) )
				{
      				$arrFields[$col['Field']] = $col;
				}
				$db->free_result($rscResult);
				// cache results for future use and return
				return $cache[$strTable] = $arrFields;
			}
			else
			{
				trigger_error("MyActiveRecord::Columns() - could not decribe table $strTable", E_USER_WARNING);
				return false;
			}
		}
	}
	
	/**
	 * Gets the 'type' of a specific field in a specified table
	 * (i.e 'int'|'float'|'date'|'char'|'text')
	 *
	 * @static
	 * @param	string	strTable	Name of database table
	 * @param	string	strField	Name of field in table
	 * @return	string 	Field type (e.g. 'int'|'float'|'date'|'char'|'text' ). 
	 *					False if not found.
	 */
	function GetType($strTable, $strField)
	{
		$fields = MyActiveRecord::Columns($strTable);
		if( isset($fields[$strField]['Type']) )
		{
      		$type_len = explode( '(', $fields[$strField]['Type'] );
      		$type = $type_len[0];
      		return $type;
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * gets the maximum allowed length of a specified field in a specified 
	 * table
	 *
	 * @static
	 * @param	string	strTable	Name of database table
	 * @param	string	strField	Name of field in table
	 * @return	integer	Maximum length of field. False if not found.
	 */
	function GetLen($strTable, $strField)
	{
		$fields=MyActiveRecord::Columns($strTable);
		if( isset($fields[$strField]['Type']) )
		{
      		$type_len = explode( '(', $fields[$strField]['Type'] );
      		$length = array_key_exists(1, $type_len) ? str_replace(')', '', $type_len[1]) : false;
      		return $length;
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * finds out whether a NULL value is allowed in a specified field in a 
	 * specified table 
	 *
	 * @static
	 * @param	string	strTable	Name of database table
	 * @param	string	strField	Name of field in table
	 * @return	boolean	True if this field allows nulls. False if not.
	 */
	function AllowNull($strTable, $strField) {
		$fields=MyActiveRecord::Columns($strTable);
		if( isset($fields[$strField]['Null']) ) {
			return $fields[$strField]['Null'];
  		} else {
			return false;
		}
	}
	
	/**
	 * Escapes a value against a field type in preparation for adding to a sql
	 * query. Escaping includes wrapping the value in single quotes
	 *
	 * @static
	 * @param	mixed	mixVal	value, eg: true, 1, 'elephant' etc.
	 * @return	mixed	escaped value eg: 1, 'o\'reilly' etc.
	 */
	function Escape($mixVal)
	{
$db =& Database::getInstance();
		// clean whitespace
		$val = trim( $mixVal );		
		// magic quotes?
		if ( get_magic_quotes_gpc() )
		{
			$val = stripslashes($val);
		}
		return("'".$db->escape_string($val)."'");
	}
		
	/**
	 * Given the names of two classes/database tables this method returns
	 * the name of the table which would link them in a many-to-many 
	 * relationship e.g: 
	 * <code>
	 * print GetLinkTable('Driver', 'Car')	// Car_Driver
	 * </code>
	 *
	 * note that the linking table will order the names of the tables it links
	 * alphabetically. If you intend to have a many-to-many relationship 
	 * between two classes, you need to create this table in your database. 
	 * The table should have two indexed fields, providing foreign keys to the 
	 * tables they link, e.g. Driver_id, Car_id
	 *
	 * NB: This function does NOT check that the table actually exists in the
	 * database, but presumes that it does.
	 *
	 * @static
	 * @param	string	strClass1	name of first class/table, e.g. 'Person'
	 * @param	string	strClass2	name of second class/table e.g. 'Role'
	 * @return	string	name of linking table
	 */
	function GetLinkTable($strClass1, $strClass2)
	{
		$array = array( MyActiveRecord::Class2Table($strClass1), MyActiveRecord::Class2Table($strClass2) );
		sort($array);
		return implode( '_', $array);
	}
	
	/**
	 * Links two objects together. Presumes the existance of a linking table.
	 *
	 * @static
	 * @param	object	$obj1	An Object from a subclass of MyActiveRecord
	 * @param	object	$obj2	An Object from a subclass of MyActiveRecord
	 * @return	boolean	true on success, false on failure
	 * @see	GetLinkTable()	
	 */
	function Link(&$obj1, &$obj2)
	{
		$table1=MyActiveRecord::Class2Table($obj1);
		$table2=MyActiveRecord::Class2Table($obj2);
		$linktable = MyActiveRecord::GetLinkTable($table1, $table2);
		$sql = "INSERT INTO {$linktable} ({$table1}_id, {$table2}_id) VALUES ({$obj1->id}, {$obj2->id})";
		if( MyActiveRecord::Query($sql) )
		{
			return true;
		}
		else
		{
			trigger_error("MyActiveRecord::Link() - Failed to link objects: $table1, $table2", E_USER_WARNING);
			return false;
		}
	}
	
	/**
	 * Destroys a link between two objects where it exists
	 *
	 * @static
	 * @param	$obj1	An Object from a subclass of MyActiveRecord
	 * @param	$obj2	An Object from a subclass of MyActiveRecord
	 * @return	true on success, false on failure
	 */
	function UnLink(&$obj1, &$obj2)
	{
		$table1=MyActiveRecord::Class2Table($obj1);
		$table2=MyActiveRecord::Class2Table($obj2);
		$linktable = MyActiveRecord::GetLinkTable($table1, $table2);
		$sql = "DELETE FROM {$linktable} WHERE {$table1}_id = {$obj1->id} AND {$table2}_id = {$obj2->id}";
		if( MyActiveRecord::Query($sql) )
		{
			return true;
		}
		else
		{
			trigger_error("MyActiveRecord::UnLink() - Failed to unlink objects: $table1, $table2", E_USER_WARNING);
			return false;
		}
	}
	
	/**
	 * Creates a new object of class strClass. strClass should be 
	 * an extension of MyActiveRecord. arrVals is an optional array of values.
	 * e.g.:
	 * <code>
	 * $person = MyActiveRecord::Create('Person', array( first_name=>'Jake', last_name=>"Grimley' ) );
	 * </code>
	 *
	 * @static
	 * @param	strClass, the name of the subclass.
	 * @return	object	of class strClass
	 */
	function &Create($strClass, $arrVals = null)
	{
		$obj = new $strClass();
		foreach( MyActiveRecord::Columns( $strClass ) as $key=>$field )
		{
			$obj->$key = $field['Default'];
		}
		$obj->populate($arrVals);		
		return $obj;
	}
	
	/**
	 * Counts the number of rows in the database matching the optional 
	 * condition. eg:
	 * <code>
	 * print 'There are '.MyActiveRecord::Count('Person').' People in the database.';
	 * print 'There are '.MyActiveRecord::Count('Person', "last_name LIKE 'Smith'").' People with the surname Smith';
	 * </code>
	 *
	 * @static
	 * @param	string	strClass, the name of the class for which you want to create an object.
	 * @return	integer Count. False if the query fails.
	 */
	function Count( $strClass, $strWhere='1=1' )
	{
$db =& Database::getInstance();
		$table = MyActiveRecord::Class2Table($strClass);
		$strSQL = "SELECT Count(id) AS count FROM $table WHERE $strWhere";
		$rscResult = MyActiveRecord::Query($strSQL);
		if( $arr = $db->fetch_array($rscResult) )
		{
			return $arr['count'];
		}
		else
		{
			return false;
		}
	}
		
	/**
	 * Returns an array of objects of class strClass mapped from SQL query 
	 * strSQL. eg:
	 * <code>
	 * $people = MyActiveRecord::('Person', 'SELECT * FROM person ORDER BY first_name');
	 * foreach( $people as $person ) print $person->first_name;
	 * </code>
	 *
	 * @static
	 * @param	string	strClass	The name of the class for which you want to return objects.
	 * @param	string	strSQL	The SQL query
	 * @return	array	array of objects of class strClass
	 */
	function FindBySql( $strClass, $strSQL,  &$foundrows=NULL, $strIndexBy='id') {
	
$db =& Database::getInstance();
		static $cache = array();
		$md5 = md5($strSQL);
	//echo $strSQL;
		if( isset( $cache[$md5] ) && defined('MYACTIVERECORD_CACHE_SQL') && MYACTIVERECORD_CACHE_SQL ) {
			return $cache[$md5];
		} else {	
			if( $rscResult = MyActiveRecord::query($strSQL) ) {

$foundrows = $db->found_rows();

//echo $count;
				$arrObjects = array();
				while( $arrVals = $db->fetch_assoc($rscResult) ) {
					//$arrObjects[$arrVals[$strIndexBy]] =& MyActiveRecord::Create($strClass, $arrVals );
					$arrObjects[] =& MyActiveRecord::Create($strClass, $arrVals );
//print_r($arrObjects[$arrVals[$strIndexBy]]);
				}

//$db =& Database::getInstance();

//$count = $db->found_rows();
//echo $strSQL;
//echo $count;

				$db->free_result($rscResult);
				return $cache[$md5]=$arrObjects;
			} else {
				trigger_error("MyActiveRecord::FindBySql() - SQL Query Failed: $strSQL", E_USER_ERROR);
				return $cache[$md5]=false;
			}
		}
	}
	
	/**
	 * Returns an array of all objects of class strClass found in database
	 * optional where, order and limit paramaters enable the results to be 
	 * narrowed down. eg:
	 * <code>
	 * $cars = MyActiveRecord::FindAll('Car');
	 * $cars = MyActiveRecord::FindAll('Car', "colour='red'", 'make ASC', 10);
	 * </code>
	 *
	 * @static
	 * @param 	string	strClass	the name of the class for which you want objects
	 * @param 	mixed	mxdWhere	optional SQL WHERE fragment, eg: "username='fred' AND password='123'"
	 *								can also be expressed as array, e.g. array( 'username'=>'fred', password=>'123')
	 * @param	string	strOrderBy	optional SQL ORDER BY fragment, eg: "username ASC"
	 * @param	string	intLimit	optional integer limiting the number of records returned
	 * @param	string	intOffset	optional integer to offset the first record brought back
	 * @return	array	Array of objects. Array is empty if no ojbects found
	 */
	function FindAll( $strClass, $mxdWhere=NULL, $strOrderBy='id ASC', $intLimit=10000, $intOffset=0, &$foundrows=NULL)
	{
		$table = MyActiveRecord::Class2Table($strClass);
		$strSQL = "SELECT SQL_CALC_FOUND_ROWS * FROM $table";
		if($mxdWhere)
		{
			$strWhere = ' WHERE ';
			if( is_array($mxdWhere) )
			{
				$conditions = array();
				foreach($mxdWhere as $key=>$val)
				{
					$val = addslashes($val);
					$conditions[]="$key='$val'";
				}
				$strWhere.= implode(' AND ', $conditions);
			}
			elseif( is_string($mxdWhere) )
			{
				$strWhere.= $mxdWhere;
			}
			$strSQL.=$strWhere;
		}
		// check for single-table-inheritance
		if( strtolower($strClass) != $table )
		{
			$strSQL.= $mxdWhere ? " AND class LIKE '$strClass' ":" WHERE class LIKE '$strClass' ";
		}
		$strSQL.=" ORDER BY $strOrderBy LIMIT $intOffset, $intLimit";

		$result = MyActiveRecord::FindBySql( $strClass, $strSQL , $foundrows, $strOrderBy);

		return $result;
	}
	
	/**
	 * Returns the first object of class strClass found in database
	 * optional where, order and limit paramaters enable the results to be 
	 * narrowed down
	 * eg:
	 * <code>
	 * $car = MyActiveRecord::FindFirst('Car', "colour='red'", 'model ASC');
	 * </code>
	 *
	 * @static
	 * @param	strClass	string, the name of the class for which you want objects
	 * @param	strWhere	optional SQL WHERE argument, eg: "username='fred' AND password='123'"
	 * @param	strOrderBy	optional SQL ORDER BY argument, eg: "username ASC"
	 * @return	object, false if no objects found
	 */	
	function FindFirst( $strClass, $strWhere=NULL, $strOrderBy='id ASC' )
	{
		$arrObjects = MyActiveRecord::FindAll( $strClass, $strWhere, $strOrderBy, 1 );
		if( Count($arrObjects) )
		{
			return array_shift($arrObjects);
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * Returns an object of class strClass found in database with a specific 
	 * integer ID. An array of integers can be passed in order to retrieve an 
	 * array of objects with matching IDs
	 * eg:
	 * <code>
	 * $car = MyActiveRecord::FindById(15);
	 * $cars = MyActiveRecord::FindById(3, 5, 13);
	 * </code>
	 *
	 * @static
	 * @param	string	strClass	the name of the class for which you want objects
	 * @param	mixed	mxdID	integer or array of integers
	 * @return	mixed	object, or array of objects
	 */	
	function FindById( $strClass, $mxdID )
	{
		if( is_array($mxdID) )
		{
			$idlist = implode(', ', $mxdID);
			return MyActiveRecord::FindAll( $strClass, "id IN ($idlist)" );
		}
		else
		{
			$id = intval($mxdID);
			return MyActiveRecord::FindFirst( $strClass, array('id'=>$id) );
		}
	}
	
	/**
	* Static Method to retrieve an array of unique values for a table/column
	* along with the total records featuring that unique value
	* eg:
	* <code>
	* foreach( MyActiveRecord::FreqDist('Person', 'first_name') as $name=>$total )
	* {
	*	print "There are $total people with the first name '$name'";
	* }
	* </code>
	*
	* @static
	* @param	string	strTable	name of database table
	* @param	string	strColumn	name of column in table
	* @param	string	strOrder	optional sql ORDER fragment (i.e. 'name ASC')
	* @param	integer	limit		optional sql LIMIT to number of rows returned
	* @return	array 	array with keys containing distinct values and values containing totals
	*/
	function FreqDist($strTable, $strColumn, $strWhere='1=1', $strOrder=null, $intLimit=1000)
	{
$db =& Database::getInstance();
		$table = MyActiveRecord::Class2Table($strTable);
		$arr = array();
		$strOrder = $strOrder ? $strOrder:$strColumn;
		$result = MyActiveRecord::Query("SELECT $strColumn, count(*) AS frequency FROM $table WHERE $strWhere GROUP BY $strColumn ORDER BY $strOrder LIMIT $intLimit");
		while( $row = $db->fetch_row($result) )
		{
			$arr[$row[0]] = $row[1];
		}
		return $arr;
	}
	
	/**
	* Static method to insert a new row into the database using class strClass
	* and using the values in properties
	* eg:
	* <code>
	* MyActiveRecord::Insert( 'Car', array('make'=>'Citroen', 'model'=>'C4', 'colour'=>'Silver') );
	* </code>
	*
	* @static
	* @param	string	strClass	the name of the class/table
	* @param	array	properties	array/hash of properties for object/row
	* @return	boolean	true or false depending upon whether insert is successful
	*/
	function Insert( $strClass, $properties )
	{
		$object = MyActiveRecord::Create($strClass, $properties);
		return $object->save;
	}

	/**
	* Static method to update a row in the database using class strClass
	* and using the values in properties
	* eg:
	* <code>
	* MyActiveRecord::Update( 'Car', 1, array('make'=>'Citroen', 'model'=>'C4', 'colour'=>'Silver') );
	* </code>
	*
	* @static
	* @param	string	strClass	the name of the class/table
	* @param	int		id			the id of the row be updated.	
	* @param	arrray	properties	array/hash of properties for object/row
	* @param	boolean true or false depending upon whether update is sucessful
	*/	
	function Update( $strClass, $id, $properties )
	{
		$object = MyActiveRecord::FindById($strClass, $id);
		$object->populate(properties);
		return $object->save();
	}
	
	/**
	* Static method to begin a transaction
	* @static
	*/
	function Begin()
	{
		MyActiveRecord::Query('BEGIN');
	}

	/**
	* Static method to roll back a transaction
	* @static
	*/	
	function RollBack()
	{
		MyActiveRecord::Query('ROLLBACK');
	}

	/**
	* Static method to commit a transaction
	* @static
	*/		
	function Commit()
	{
		MyActiveRecord::Query('COMMIT');
	}
		
	/**
	 * Saves the object back to the database
	 * eg: 
	 * <code>
	 * $car = MyActiveRecord::Create('Car');
	 * print $car->id;	// NULL
	 * $car->save();
	 * print $car->id; // 1
	 * </code>
	 *
	 * NB: if the object has registered errors, save() will return false
	 * without attempting to save the object to the database
	 *
	 * @return	boolean	true on success false on fail
	 */
	function save() {
		$db =& Database::getInstance();
		// if this object has registered errors, we back off and return false.
		if( $this->get_errors() )
		{
			return false;
		}
		else
		{
			$table = MyActiveRecord::Class2Table(get_class($this));
			
			// check for single-table-inheritance
			if( strtolower(get_class($this)) != $table )
			{
				$this->class = get_class($this);
			}
			
			$fields = MyActiveRecord::Columns($table);
			// sort out key and value pairs
			foreach ( $fields as $key=>$field )
			{
				if($key!='id')
				{
					$val = MyActiveRecord::Escape( isset($this->$key) ? $this->$key : null  );
					$vals[]=$val;
					$keys[]="`".$key."`";
					$set[] = "`$key` = $val";
				}
			}
			// insert or update as required
			if( isset($this->id) ) {
				$sql="UPDATE `$table` SET ".implode($set, ", ")." WHERE id={$this->id}";
			}
			else
			{
				$sql="INSERT INTO `$table` (".implode($keys, ", ").") VALUES (".implode($vals, ", ").")";
			}
			$success = MyActiveRecord::Query($sql);
			if( !isset($this->id) )
			{
				$this->id=$db->insert_id();
			}
			return $success;
		}
	}
 
 	/**
 	 * Sets all object properties via an array
 	 *
 	 * @param	array	$arrVals	array of named values
 	 * eg:
 	 * <code>
 	 * $car->populate( array('make'=>'Citroen', 'model'=>'C4', 'colour'=>'red') );
 	 * $car->populate( $_POST );
 	 * </code>
 	 *
 	 * @param	array	$arrVals
 	 * @return	boolean	true if $arrVals is valid array, false if not
 	 */
	function populate($arrVals)
	{
		if( is_array($arrVals) )
		{
			foreach($arrVals as $key=>$val)
			{
				$this->$key=$val;
			}
			return true;
		}
		else
		{
			return false;
		}
	}
		
	/**
	 * Deletes the object from the database
	 * eg:
	 * <code>
	 * $car = MyActiveRecord::FindById('Car', 1);
	 * $car->destroy();
	 * </code>
	 *
	 * @return	boolean	True on success, False on failure
	 */
	function destroy()
	{
		$table = MyActiveRecord::Class2Table($this);
		return MyActiveRecord::Query( "DELETE FROM $table WHERE id ={$this->id}" );
	}
	
	/**
	 * alias of destroy()
	 * @see destroy()
	 */
	function delete()
	{
		return $this->destroy();
	}
	
	/*
	 * Adds a child object of class strClass to this object
	 * eg:
	 * <code>
	 * $driver = MyActiveRecord::FindById('Driver', 1);
	 * $car = $driver->add_child('Car', array('make'=>'citroen', model'=>'c4') );
	 * $car->save();
	 * </code>
	 * @param string	strClass	class of object we wish to add
	 * @param properties array 	optional array of properties for new object
	 * @return	object	object of class 'strClass'
	 */
	function add_child($strClass, $properties=null)
	{
		$object = MyActiveRecord::Create($strClass, $properties);
		$key = MyActiveRecord::Class2Table($this)."_id";
		$object->$key = $this->id;
		return $object;
	}
	
	/**
	 * Attaches another object to the object
	 * NB: You must have saved the object you want to attach before attaching 
	 * it eg:
	 * <code>
	 * $post = MyActiveRecord::Create('Post');
	 * $post->populate( 'title'=>'New Post' );
	 * $post->save();
	 * $topic->attach('post');
	 * </code>
	 *
	 * @param	object	$obj	the object you wish to attach
	 * @return	boolean	True on success. False on failure.
	 */
	function attach(&$obj)
	{
		if( $this->id && $obj->id )
		{
			return MyActiveRecord::Link($this, $obj);
		}
		else
		{
			trigger_error('MyActiveRecord::attach() - both objects must be saved before you can attach');
			return false;
		}
	}
	
	/**
	 * Detaches an object from the object
	 * eg:
	 * <code>
	 * // detach old posts
	 * foreach( $topic->find_attached('Post') as $post )
	 * {
	 * 	if( $post->created < mktime()-5000000 )
	 *	{
	 *		$topic->detach($post);
	 *	}
	 * }
	 * </code>
	 *
	 * @param	object	$obj	object to be detached
	 */	
	function detach(&$obj)
	{
		return MyActiveRecord::UnLink($this, $obj);
	}
	
	/**
	 * Sets all attached links via an array of IDs
	 * e.g.
	 * <code>	
	 * $topic->set_attached('Post', array(1, 8, 32) );
	 * $topic->set_attached('Post', $_POST['id_list']);
	 * </code>
	 * NB: set_attached will destroy any existing attachments for the class strClass
	 * BEFORE creating new attachments
	 *
 	 * @param	string	strClass	class of objects to be set as attached
	 * @param	array	arrID		array of object IDs
	 * @return	boolean	True on success. False on failure.
	 */
	function set_attached($strClass, $arrID)
	{
		if( is_array($arrID) )
		{
			MyActiveRecord::Begin();
			$pass = true;
			foreach( $this->find_linked($strClass) as $fObject )
			{
				$this->detach($fObject) or $pass=false;
			}
			foreach( MyActiveRecord::FindById($strClass, $arrID) as $fObject )
			{
				$this->attach($fObject) or $pass=false;
			}
			$pass ? MyActiveRecord::Commit() : MyActiveRecord::RollBack();
			return $pass;
		}
		else
		{
			trigger_error('MyActiveRecord::set_attached() - Second argument not an array', E_USER_NOTICE);
			return false;
		}
	}

	
	/**
	* Sets the date of the property specified by strKey
	* @param	string	strKey	property to be set
	* @param	int	intTimeStamp	unix timestamp	
	*/
	function set_date($strKey, $intTimeStamp=null)
	{
		$this->$strKey = MyActiveRecord::DbDate($intTimeStamp);
	}
	
	/**
	* Sets the datetime of the property specified by strKey
	* @param	string	strKey	property to be set
	* @param	int	intTimeStamp	unix timestamp	
	*/
	function set_datetime($strKey, $intTimeStamp=null)
	{
		$this->$strKey = MyActiveRecord::DbDateTime($intTimeStamp);
	}
	
	/**
	* Retrieves a date or datetime fields as a unix timestamp
	* @param	string	strKey	property to be retrieved
	*/
	function get_timestamp($strKey)
	{
		return MyActiveRecord::TimeStamp($this->$strKey);
	}
	
	/**
	 * returns 'parent' object.
	 * e.g.
	 * <code>
	 * $topic = $post->find_parent('Topic');
	 * </code>
	 * 
	 * In order for the above to work, you would need to have an integer 
	 * field called 'Topic_id' in your 'Post' table. MyActiveRecord will take 
	 * care of the rest.
	 *
	 * @param	string	strClass	Name of the class of objects to return in the array
	 * @param	string	strForeignKey	Optional specification of foreign key at runtime
	 * @return	object	object of class strClass
	 */
	function find_parent($strClass, $strForeignKey=NULL)
	{
		$key = $strForeignKey or $key=strtolower( $strClass.'_id' );
		return MyActiveRecord::FindById($strClass, $this->$key);
	}
	
	/**
	 * returns array of 'child' objects.
	 * e.g.
	 * <code>
	 * foreach( $topic->find_children('Post') as $post ) print $post->subject;
	 * </code>
	 * 
	 * In order for the above to work, you would need to have an integer field called 'Topic_id'
	 * in your 'Post' table. MyActiveRecord will take care of the rest.
	 *
	 * @param	string	strClass	Name of the class of objects to return in the array
	 * @param	mixed mxdCondition Either a SQL 'WHERE' fragment or an array of paramaters that must be matched ( see FindAll() )
	 * @param	string strOrderBy	a SQL ORDER BY strring fragment
	 * @param	integer	intLimit	limit on number of records to retrieve
	 * @param	integer	intOffset	if you don't want to begin with the first child you can add an offset here
	 * @param	strForeignKey	if the foreign key is not parent_id but something different you can specify here
	 * @param	mixed	optional sql condition expressed as either a sql string or an array
	 *					eg:	'flagged=true' or array( 'flagged'=>1 );
	 * @return	array	array containing objects of class strClass
	 */
	function find_children($strClass, $mxdCondition=NULL, $strOrderBy='id ASC', $intLimit=10000, $intOffset=0, $strForeignKey=NULL)
	{
		// name of foreign key:
		$key = $strForeignKey ? $strForeignKey : strtolower( get_class($this).'_id' );
				
		if( is_array($mxdCondition) || is_null($mxdCondition) )
		{
			// specifiy condition as an array
			$mxdCondition[$key]=$this->id;
			return MyActiveRecord::FindAll( $strClass, $mxdCondition, $strOrderBy, $intLimit, $intOffset );
		}
		else
		{
			// condition is non-null string
			$fullSQLCondition = "$key=$this->id AND ($mxdCondition)";
			return MyActiveRecord::FindAll( $strClass, $fullSQLCondition, $strOrderBy, $intLimit, $intOffset );
		}
	}
	
	/**
	 * returns array of 'linked' objects. (many-to-many relationship)
	 * e.g.
	 * <code>
	 * foreach( $user->find_linked('Role') as $role ) print $role->name;
	 * </code>
	 * 
	 * In order for the above to work, you would need to have a linking table
	 * called Role_User in your database, containing the fields role_id and user_id
	 *
	 * @param	string	strClass	Name of the class of objects to return in the array
	 * @param	string	strCondition	Optional SQL condition, e.g. 'password NOT NULL'
	 * @return	array	array containing objects of class strClass
	 *
	 */	
	function find_linked($strClass, $mxdCondition=null, $strOrder=null)
	{
		if($this->id)
		{
			// only attempt to find links if this object has an id
			$table = MyActiveRecord::Class2Table($strClass);
			$thistable = MyActiveRecord::Class2Table($this);
			$linktable=MyActiveRecord::GetLinkTable($table, $thistable);
			$strOrder = $strOrder ? $strOrder: "{$strClass}.id";
			$sql= "SELECT {$table}.* FROM {$table} INNER JOIN {$linktable} ON {$table}_id = {$table}.id WHERE $linktable.{$thistable}_id = {$this->id} ORDER BY $strOrder";
			if( is_array($mxdCondition) )
			{
				foreach($mxdCondition as $key=>$val)
				{
					$val = addslashes($val);
					$sql.=" AND $key = '$val' ";
				}
			}
			else
			{
				if($mxdCondition) $sql.=" AND $mxdCondition";
			}
			return MyActiveRecord::FindBySql($strClass, $sql);
		}
		else
		{
			return array();
		}
	}
	
	/**
	 * Alias of find_linked()
	 * @link find_linked()
	 */
	function find_attached($strClass, $strCondition=NULL)
	{
		return $this->find_linked($strClass, $strCondition);
	}
	
	/**
	 * Adds an error to the object. The existence of errors
	 * ensures that $object->save() will return false and
	 * will not attempt to persist the object to the database
	 * This can be used for validation of the object.
	 * e.g.
	 * <code>
	 * if( empty( $user->first_name ) ) $user->add_error('first_name', 'First Name may not be blank!');
	 * $user->save or print_r($user->get_errors)
	 * </code>
	 *
	 * @param	string	strKey	the name of the invalid key/property/attribute
	 * @param	string	strMessage	a message, which you may want to report back to the user in due course
	 * @return	void
	 */
	function add_error($strKey, $strMessage)
	{
		if(!isset($this->_errors)) $this->_errors = array();
		$this->_errors[$strKey] = $strMessage; 
	}
	
	/**
	 * Gets an error on a specified attribute.
	 * 
	 * @param	string	strKey	name of field/attribute/key
	 * @return	string	Error Message. False if no error
	 */
	function get_error($strKey)
	{
		if( isset($this->_errors[$strKey]) )
		{
			return $this->_errors[$strKey];
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * Returns all errors.
	 *
	 * @return	array	Array of errors, keyed by attribute. 
	 *					False if there are no errors.
	 */
	function get_errors()
	{
		if( isset($this->_errors) && is_array($this->_errors) )
		{
			return $this->_errors;
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * Validates the value of an attribute against a regular
	 * expression, adding an error to the object if the value
	 * does not match.
	 *
	 * @param	string	strKey	name of field/attribute/key
	 * @param	string	strRegExp	Regular Expression
	 * @param	string	strMessage	Error message to record if value does not match
	 * @return	boolean	True if the field matches. False if it does not match.
	 */
	function validate_regexp($strKey, $strRegExp, $strMessage=null)
	{
		if( preg_match($strRegExp, $this->$strKey) )
		{
			return true;
		}
		else
		{
			$this->add_error($strKey, $strMessage ? $strMessage : 'Invalid '.$strKey);
			return false;
		}
	}
	
	/**
	 * Validates the uniqueness of the value of a given field/key.
	 * Adds error to object if field is not unique
	 * 
	 * @param	string	strKey	name of field/attribute/key
	 * @param	string	strMessage	Error message to record if value is not unique
	 * @return	boolean true if field is unique, false if not
	*/
	function validate_uniqueness_of($strKey, $strMessage=null)
	{
		if ( MyActiveRecord::Count( get_class($this), "$strKey = '{$this->$strKey}'" ) > 0 )
		{
			$this->add_error($strKey, $strMessage ? $strMessage : ucfirst($strKey).' is not unique');
			return false;
		}
		else
		{
			return true;
		}		
	}
	
	/**
	 * Returns html-escaped property value for convenience
	 * eg:
	 * <code>
	 * 	<? print $user->h('name') ?>
	 * </code>
	*/
	function h($key)
	{
		return htmlentities($this->$key);
	}
	
	function to_str()
	{
		return get_class($this).' '.$this->id;
	}

}

