<?php
/**
* Requirements : 
* 	- tested to work with MySQL 5
*	- tested to work on PHP 5
*
* Known bugs :
*	- multi field indexes, shows only the last component
*	- buggy key and index support
*	
* ToDo list :
*	- better key and index support
*	- some semantic updates
*	- input data validator
*	- support for triggers and procedures
*	- anything else feedback shows it is required ...
 *
 * @category	Database
 * @package		MyActiveRecord
 * @author		Ramon Antonio Parada <ramon@bigpress.net>
 * @copyright	2011 Ramon Antonio Parada
 * @version		0.5
*/
class MyStructMagic {

	private $mMysqlConn = false;
	private $sDatabase = '';
	private $bError = false;
	private $sError = '';
	
	/** Source refers to newer database, destination refers to database we wonna update */
	private $aSourceData = array();
	private $sSourceDataPhp = '';
	private $sSourceDataXml = '';
	private $aDestinationData = array();
	private $sDestinationDataPhp = '';
	private $sDestinationDataXml = '';
	
	
	/**
	* CONSTRUCTOR
	* public __construct, no return values
	* 
	* @param mMysqlConn, mysql connection resource
	* @param sDatabase, database name
	*/
	public function __construct() {
/*
		if( !$mMysqlConn ) {
			$this->errorHandle('No MySQL connection available', true);
			return false;
		}
		if( @get_resource_type($mMysqlConn) != 'mysql link' )
		{
			$this->errorHandle('Invalid MySql connection resource', true);
			return false;
		}
		$this->mMysqlConn = $mMysqlConn;
		if( !@$db->select_db($sDatabase, $this->mMysqlConn) )
		{
			$this->errorHandle('MySql database selection failed', true);
			return false;
		}
		$db->sql_dbase = $sDatabase;
*/
	}
	
	/**
	* public getRawDestinationData, populates aDestinationDataRaw and returns it
	* 
	*/
	public function getRawDestinationData() {
	  $db =& Database::getInstance();


		if( !empty($this->aDestinationData) ) {
			return $this->aDestinationData;
		} else {
			if( empty($this->sDestinationDataPhp) ) {
				$this->sDestinationDataPhp = $this->exportPhp($db->sql_dbase, false);
			}
			eval($this->sDestinationDataPhp);
			$this->aDestinationData = ${$db->sql_dbase};
		}
		return $this->aDestinationData;
	}


	/**
	 * Exports a table to an object
	 *
	 * @param $tablename Name of the table to export
	 * @returns Object containing the table or null if not found
	 */
	public function export_table($tablename) {
		$db =& Database::getInstance();

		$rDbTableList = $db->query("SHOW TABLE STATUS WHERE Name = '".$tablename."';");
		$aRowTableList = $db->fetch_assoc($rDbTableList);
		//TODO devolver null si no se encuentra la tabla

		$table->Name = $aRowTableList['Name'];
		
		$table->engine = $aRowTableList['Engine'];
		$table->auto_increment = ( $aRowTableList['Auto_increment'] == 'NULL' || ( empty($aRowTableList['Auto_increment']) && $aRowTableList['Auto_increment'] != 0 ) ) ? 'NULL' : $aRowTableList['Auto_increment'] ;
		$table->collation= $aRowTableList['Collation'];
		
		$rCreateTableQuery = $db->query("SHOW CREATE TABLE $tablename;");
		$aCreateTable = $db->fetch_array($rCreateTableQuery);
		$table->createtable = addslashes($aCreateTable[1]);
		//$table->createtable = $aCreateTable[1];
		

		//columns
		$table->columns =  array();
		$rTableColumnList = $db->query("SHOW FULL COLUMNS FROM $tablename;");
		while ( $aRowTableColumnList = $db->fetch_assoc($rTableColumnList) ) {
			$column = "";
			$aRowTableColumnList['Null'] = ($aRowTableColumnList['Null']=='YES') ? 'true' : 'false';
			$column->collation = ( $aRowTableColumnList['Collation']=='NULL' || empty($aRowTableColumnList['Collation']) ) ? null :$aRowTableColumnList['Collation'];
			$column->name = $aRowTableColumnList['Field'];
			$column->type = $aRowTableColumnList['Type'];
			$column->nulls = $aRowTableColumnList['Null'];//true o false en comillas
			$column->default = trim($aRowTableColumnList['Default']);
			$column->extra = $aRowTableColumnList['Extra'];
			$column->comment = $aRowTableColumnList['Comment'];
			$table->columns[] = $column;
		}
		

		//indexes
		$table->indexes =  array();
		$rTableIndexes = $db->query("SHOW INDEX FROM $tablename;");
		while ( $aRowTableIndex = $db->fetch_assoc($rTableIndexes) ) {
			$index = "";
			$index->name = $aRowTableIndex['Key_name'];
			$index->nulls = ( $aRowTableIndex['Null'] == 'NULL' || empty($aRowTableIndex['Null']) );
			$index->non_unique = $aRowTableIndex['Non_unique'];
			$index->seq_in_index = $aRowTableIndex['Seq_in_index'];
			$index->column_name = $aRowTableIndex['Column_name'];
			$index->collation = trim($aRowTableIndex['Collation']);
			$index->index_type = $aRowTableIndex['Index_type'];
			$table->indexes[] = $index;
		}

		return $table;
	}





	
	/**
	* public exportPhp, if sFileName false, returns php code insted of writing to file, else return true on success
	*
	* @param sVarName
	* @param sFileName
	*/
	public function exportPhp($sVarName="magic",$sFileName=false) {
	  $db =& Database::getInstance();


		if( empty($this->sDestinationDataPhp) )
		{
			$sVarName = ($sVarName) ? $sVarName : 'database';
			$this->sDestinationDataPhp = '$MyStructMagic_sVarName="'.$sVarName.'";'."\n";
			$this->sDestinationDataPhp .= '$'.$sVarName.' = array(';
			$this->sDestinationDataPhp .= '\'name\' => \''.$db->sql_dbase.'\',';

			// TODO : Database variables : show variables like "collation_database";show variables like "character_set_database";
			
				$this->sDestinationDataPhp .= '\'tables\' => array(';
				$rDbTableList = $db->query("SHOW TABLE STATUS;");
				while( $aRowTableList = $db->fetch_assoc($rDbTableList) )
				{
					$sTableName = $aRowTableList['Name'];
					$this->sDestinationDataPhp .= '\''.$sTableName.'\' => array('."\n";
						
						$this->sDestinationDataPhp .= '\'engine\' => \''.$aRowTableList['Engine'].'\','."\n";
						$aRowTableList['Auto_increment'] = ( $aRowTableList['Auto_increment'] == 'NULL' || ( empty($aRowTableList['Auto_increment']) && $aRowTableList['Auto_increment'] != 0 ) ) ? 'NULL' : $aRowTableList['Auto_increment'] ;
						$this->sDestinationDataPhp .= '\'auto_increment\' => \''.$aRowTableList['Auto_increment'].'\','."\n";
						$this->sDestinationDataPhp .= '\'collation\' => \''.$aRowTableList['Collation'].'\','."\n";
						
						$rCreateTableQuery = $db->query("SHOW CREATE TABLE $sTableName;");
						$aCreateTable = $db->fetch_array($rCreateTableQuery);
						$this->sDestinationDataPhp .= '\'createtable\' => \''.addslashes($aCreateTable[1]).'\','."\n";
						
						$this->sDestinationDataPhp .= '\'columns\' => array(';
						$rTableColumnList = $db->query("SHOW FULL COLUMNS FROM $sTableName;");
						while( $aRowTableColumnList = $db->fetch_assoc($rTableColumnList) )
						{
							$aRowTableColumnList['Null'] = ($aRowTableColumnList['Null']=='YES') ? 'true' : 'false';
							$aRowTableColumnList['Collation'] = ( $aRowTableColumnList['Collation']=='NULL' || empty($aRowTableColumnList['Collation']) ) ? '' : '\'collation\'=>\''.$aRowTableColumnList['Collation'].'\',';
							$this->sDestinationDataPhp .= '\''.$aRowTableColumnList['Field'].'\' => array( \'type\'=>\''.$aRowTableColumnList['Type'].'\','.$aRowTableColumnList['Collation'].'\'null\'=>\''.$aRowTableColumnList['Null'].'\',\'default\'=>\''.trim($aRowTableColumnList['Default']).'\',\'extra\'=>\''.$aRowTableColumnList['Extra'].'\',\'comment\'=>\''.$aRowTableColumnList['Comment'].'\' ) , ';
						}
						$this->sDestinationDataPhp = rtrim($this->sDestinationDataPhp, ' , ');
						$this->sDestinationDataPhp .= ') , '."\n";
						
						$this->sDestinationDataPhp .= '\'indexes\' => array(';
						$rTableIndexes = $db->query("SHOW INDEX FROM $sTableName;");
						while( $aRowTableIndex = $db->fetch_assoc($rTableIndexes) )
						{
							$aRowTableIndex['Null'] = ( $aRowTableIndex['Null'] == 'NULL' || empty($aRowTableIndex['Null']) ) ? 'true' : 'false';
							$this->sDestinationDataPhp .= '\''.$aRowTableIndex['Key_name'].'\' => array( \'non_unique\'=>\''.$aRowTableIndex['Non_unique'].'\',\'seq_in_index\'=>\''.$aRowTableIndex['Seq_in_index'].'\',\'column_name\'=>\''.$aRowTableIndex['Column_name'].'\',\'collation\'=>\''.trim($aRowTableIndex['Collation']).'\',\'index_type\'=>\''.$aRowTableIndex['Index_type'].'\' ) , ';
						}
						$this->sDestinationDataPhp = rtrim($this->sDestinationDataPhp, ' , ');
						$this->sDestinationDataPhp .= ') , '."\n";
					
					$this->sDestinationDataPhp = rtrim($this->sDestinationDataPhp, ' , ');
					$this->sDestinationDataPhp .= ') , '."\n"."\n";
					
				}
				$this->sDestinationDataPhp = rtrim($this->sDestinationDataPhp, ' , ');
				$this->sDestinationDataPhp .= ')';
				
			$this->sDestinationDataPhp .= ');';
			
			$db->free_result($rDbTableList);
			$db->free_result($rTableColumnList);
			$db->free_result($rCreateTableQuery);
			$db->free_result($rTableIndexes);
			
		}
		if($sFileName) {
			$rHandle = fopen($sFileName, 'w+');
			fwrite($rHandle, '<?php'."\n".$this->sDestinationDataPhp.'?>');
			fclose($rHandle);
		}
		else
		{
			return $this->sDestinationDataPhp;
		}
		return true;
	}
	
	/**
	* public exportXtml, if sFileName false, returns xml code insted of writing to file, else return true on success
	*
	* @param sFileName, full filename where to export xml
	*/
	public function exportXtml($sFileName=false) {
	  $db =& Database::getInstance();

		//if( empty($this->sDestinationDataXml) ) {

			$this->sDestinationDataXml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>"."\n";
//echo $this->sDestinationDataXml."nn\n";
			$this->sDestinationDataXml .= "<database name=\"".$db->sql_dbase."\">"."\n";
			$rDbTableList = $db->query("SHOW TABLE STATUS;");

//echo $this->sDestinationDataXml."nn\n";
//TODO pasar lo d euna tabla para otra funcion para poder exportar una sola
//			$rDbTableList = $db->query("SHOW TABLE STATUS WHERE Name = "";");
			while( $aRowTableList = $db->fetch_assoc($rDbTableList) ) {
//echo $aRowTableList['Name']."\n";
				$sTableName = $aRowTableList['Name'];
				$aRowTableList['Auto_increment'] = ( $aRowTableList['Auto_increment'] == 'NULL' || ( empty($aRowTableList['Auto_increment']) && $aRowTableList['Auto_increment'] != 0 ) ) ? 'NULL' : $aRowTableList['Auto_increment'] ;
				$this->sDestinationDataXml .= "\t".'<table name="'.$sTableName.'" engine="'.$aRowTableList['Engine'].'" auto_increment="'.$aRowTableList['Auto_increment'].'" collation="'.$aRowTableList['Collation'].'">'."\n";
				
				$rTableColumnList = $db->query("SHOW FULL COLUMNS FROM $sTableName;");
				while( $aRowTableColumnList = $db->fetch_assoc($rTableColumnList) )
				{
					$aRowTableColumnList['Null'] = ($aRowTableColumnList['Null']=='YES') ? 'true' : 'false';
					$aRowTableColumnList['Collation'] = ( $aRowTableColumnList['Collation']=='NULL' || empty($aRowTableColumnList['Collation']) ) ? '' : 'collation="'.$aRowTableColumnList['Collation'].'"';
					$this->sDestinationDataXml .= "\t\t".'<column name="'.$aRowTableColumnList['Field'].'" type="'.$aRowTableColumnList['Type'].'" '.$aRowTableColumnList['Collation'].' null="'.$aRowTableColumnList['Null'].'" default="'.$aRowTableColumnList['Default'].'" extra="'.$aRowTableColumnList['Extra'].'" comment="'.$aRowTableColumnList['Comment'].'" />'."\n";
				}
				
				$rTableIndexList = $db->query("SHOW INDEX FROM $sTableName;");
				while( $aRowTableIndexList = $db->fetch_assoc($rTableIndexList) ) {
					$aRowTableIndexList['Null'] = ( $aRowTableIndexList['Null'] == 'NULL' || empty($aRowTableIndexList['Null']) ) ? 'true' : 'false';
					$this->sDestinationDataXml .= "\t\t".'<index name="'.$aRowTableIndexList['Key_name'].'" non_unique="'.$aRowTableIndexList['Non_unique'].'" seq_in_index="'.$aRowTableIndexList['Seq_in_index'].'" column_name="'.$aRowTableIndexList['Column_name'].'" collation="'.$aRowTableIndexList['Collation'].'" index_type="'.$aRowTableIndexList['Index_type'].'" />'."\n";
				}
				
				$this->sDestinationDataXml .= "\t".'</table>'."\n";
//echo $this->sDestinationDataXml."aa\n";
			}
//$this->sDestinationDataXml = "ccc\n";
//echo $this->sDestinationDataXml."aa\n";
			$this->sDestinationDataXml .= "</database>"."\n";
			
//echo $this->sDestinationDataXml."aa\n";
			$db->free_result($rDbTableList);
			$db->free_result($rTableColumnList);
			$db->free_result($rTableIndexList);
	//	}

		if($sFileName) {
			$rHandle = fopen($sFileName, 'w+');
			fwrite($rHandle, $this->sDestinationDataXml);
			fclose($rHandle);
		} else {
			return $this->sDestinationDataXml;
		}
		return true;
	}
	
	/**
	 * public importPhp, no return values
	 *
	 * @param sVarName, name of variable used with export, if false, application will attempt to find it
	 * @param sFileName, filephp print_r from which to import source data
	 */
	public function importPhp($sVarName,$sFileName) {
		if( !@include($sFileName) )
		{
			$this->errorHandle('Failed to open source file.',true);
		}
		$this->aSourceData = ($sVarName) ? ${$sVarName} : ${$MyStructMagic_sVarName};
	}
	
	/**
	* public getDiffSql, returns assoc array of sql querys that should update current database structure
   * to source,array in format : label/desc=>sql
	*
	*/
	public function getDiffSql() {
	  $db =& Database::getInstance();


		if( empty($this->aDestinationData) ) {
			$this->getRawDestinationData();
		}
		if( empty($this->aDestinationData) ) {
			$this->errorHandle('Failed to populate destination data.',true);
		}
		if( empty($this->aSourceData) ) {
			$this->errorHandle('No source data found.',true);
		}
		
		foreach ( $this->aSourceData['tables'] as $sTable => $mTableData ) {
			$mFields = $mTableData['columns'];
			$aSourceTableFieldsKeys = array_keys($mFields);
		
			if( !array_key_exists($sTable, $this->aDestinationData['tables']) )
			{
				$aDifferenceSql["Create table $sTable"] = stripslashes($mTableData['createtable']);
			}
			else
			{	
				if( $mTableData['engine'] != $this->aDestinationData['tables'][$sTable]['engine'] ) {
					$aDifferenceSql["Engine of table $sTable"] = "ALTER TABLE `$sTable` ENGINE = ".$mTableData['engine'].";";
				}
				if( $mTableData['collation'] != $this->aDestinationData['tables'][$sTable]['collation'] ) {
					$aDifferenceSql["Collation of table $sTable"] = "ALTER TABLE `$sTable` COLLATION = ".$mTableData['collation'].";";
				}
				
				$aDestinationTableFieldsKeys = array_keys($this->aDestinationData['tables'][$sTable]['columns']);
				$iLookOffset = 0;
				reset($aSourceTableFieldsKeys);
				foreach($mFields as $sField => $mParams) {
					$iKeyIndex = key($aSourceTableFieldsKeys);
					if( !array_key_exists($sField, $this->aDestinationData['tables'][$sTable]['columns']) ) {
						$iLookOffset--;
						if($iKeyIndex==0) {
							$sPosition = "FIRST";
						} else {
							$sPosition = "AFTER `".$aSourceTableFieldsKeys[$iKeyIndex-1]."`";
						}
						$sType = $mParams['type'];
						$sCollation = ( $mParams['collation']=="NULL" || empty($mParams['collation']) ) ? "" : "COLLATE ".$mParams['collation'];
						$mNull = ( $mParams['null']=='true' ) ? "" : "not null";
						$mDefault = ( strlen($mParams['default']) == 0 ) ? "" : "default '".$mParams['default']."'";
						$mExtra = ( $mParams['extra'] == "auto_increment" ) ? "AUTO_INCREMENT PRIMARY KEY" : $mParams['extra'];
						$sComment = $mParams['comment'];
						$aDifferenceSql["Add field $sField in table $sTable"] = "ALTER TABLE `$sTable` ADD `$sField` $sType $sCollation $mNull $mDefault $mExtra $sComment $sPosition;";
					} else {
						if( in_array($sField, $aDestinationTableFieldsKeys) && ($aDestinationTableFieldsKeys[$iKeyIndex+$iLookOffset+1] == $sField))
						{
							$iLookOffset++;
						}
						
						if( ($aDestinationTableFieldsKeys[$iKeyIndex+$iLookOffset] != $sField) ) {
							if( ($iKeyIndex+$iLookOffset) <= 0 ) {
								$sPosition = "FIRST";
							} else {
								$sPosition = "AFTER `".$aSourceTableFieldsKeys[$iKeyIndex-1]."`";
							}
							$sCollation = ( $mParams['collation']=='NULL' || empty($mParams['collation']) ) ? "" : "COLLATE ".$mParams['collation'];
							$sType = $mParams['type'];
							$mNull = ( $this->aSourceData['raw'][$sTable][$sField]['null']=='true' ) ? '' : 'not null';
							$mDefault = ( strlen($mParams['default']) == 0 ) ? '' : "default '".$mParams['default']."'";
							$mExtra = ( $mParams['extra'] == "auto_increment" ) ? "AUTO_INCREMENT PRIMARY KEY" : $mParams['extra'];
							$sComment = $mParams['comment'];
							$aDifferenceSql["Position of field $sField in table $sTable"] = "ALTER TABLE `$sTable` CHANGE `$sField` `$sField` $sType $sCollation $mNull $mDefault $mExtra $sComment $sPosition;";
						}
						
						foreach($mParams as $sParam => $mSetting) {
							if( !array_key_exists($sParam, $this->aDestinationData['tables'][$sTable]['columns'][$sField]) )
							{
								// never happens
							}
							elseif( $mSetting != $this->aDestinationData['tables'][$sTable]['columns'][$sField][$sParam] )
							{
								$sType = $mParams['type'];
								$sCollation = ( $mParams['collation']=='NULL' || empty($mParams['collation']) ) ? "" : "COLLATE ".$mParams['collation'];
								$mNull = ( $mParams['null']=='true' ) ? '' : 'not null';
								$mDefault = ( strlen($mParams['default']) == 0 ) ? "default ''" : "default '".$mParams['default']."'";
								$mExtra = ( $mParams['extra'] == "auto_increment" ) ? "AUTO_INCREMENT PRIMARY KEY" : $mParams['extra'];
								$sComment = $mParams['comment'];
								$aDifferenceSql["Change param $sParam in field $sField in table $sTable"] = "ALTER TABLE `$sTable` CHANGE `$sField` `$sField` $sType $sCollation $mNull $mDefault $mExtra $sComment;";
							}
						}
					}
					next($aSourceTableFieldsKeys);
				}
				
				if( is_array($mTableData['indexes']) )
				{
					foreach( $mTableData['indexes'] as $sIndexName => $mIndexData ) {
						if( !array_key_exists($sIndexName, $this->aDestinationData['tables'][$sTable]['indexes']) )
						{
							$sNon_unique = ( $mIndexData['non_unique'] == 0 ) ? "UNIQUE" : "" ;
							$sIndex_type = ( $mIndexData['index_type'] == "FULLTEXT" ) ? "FULLTEXT" : "INDEX" ;
							$sIndexColumnName = $mIndexData['column_name'];
							$aDifferenceSql["Add index/key $sIndexName in table $sTable"] = "ALTER TABLE `$sTable` ADD $sIndex_type `$sIndexName` (`$sIndexColumnName`);";
						}
						else
						{
							//alter index
						}
					}
				}
		
				//TODO : foreach table, check triggers
		
				//TODO : foreach table, check procedures
			}
		}
		
		foreach($this->aDestinationData['tables'] as $sTable=>$mTableData) {
			$mElements = $mTableData['columns'];
			if( !array_key_exists($sTable, $this->aSourceData['tables']) ) {
				$aDifferenceSql["Drop table $sTable"] = "Drop table `$sTable`;";
			}
			else
			{	
				foreach($mElements as $sField => $mParams) {
					if(!array_key_exists($sField, $this->aSourceData['tables'][$sTable]['columns']))
					{
						$aDifferenceSql["Delete field $sField in table $sTable"] = "ALTER TABLE `$sTable` drop `$sField`;";
					} else {
						foreach($mParams as $sParam => $mSetting) {
							break; //params are fixed, can only change values
						}
					}
				}
				
				if( is_array($mTableData['indexes']) ) {
					foreach( $mTableData['indexes'] as $sIndexName => $mIndexData ) {
						if( !array_key_exists($sIndexName, $this->aSourceData['tables'][$sTable]['indexes']) ) {
							$aDifferenceSql["Drop index/key $sIndexName in table $sTable"] = "ALTER TABLE `$sTable` DROP INDEX `$sIndexName`;";
						}
						else
						{
							//alter index
						}
					}
				}
		
				//TODO : foreach table, check triggers
		
				//TODO : foreach table, check procedures
			}
		}
		
		return $aDifferenceSql;
	}
	
	/**
	* DESTRUCTOR
	* public __destruct, no return values,
	* params: no params
	* ! cleans all data variables
	*/
	public function __destruct() {
		unset($this->aSourceData);
		unset($this->aDestinationData);
	}
	
	/**
	* public endClean, no return values
	* params: no params
	* ! calls class descructor
	*/
	public function endClean() {
		$this->__destruct();
	}
	
	/**
	* private errorHandle , no return values
	* params :
	* @param sError : string of error message
	* @param bKillScript : boolean, true to end script with error message
	*/
	private function errorHandle($sError,$bKillScript=false) {
		$this->bError = true;
		$this->sError = "MySql StructMagic Error : " . $sError;
		if($bKillScript) {
			exit($this->sError);
		}
	}
	
	/**
	* public getError, returns error message or false if no error
	*
	*/
	public function getError() {
		return ( $this->bError ) ? $this->sError : false ;
	}
}
