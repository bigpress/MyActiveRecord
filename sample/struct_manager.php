<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<title>MySQL StructMagic - Demo</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="Rating" content="Restricted" />
		<meta name="Robots" content="NOINDEX,NOFOLLOW" />
	</head>
	
	<body>
		<h1>MySQL Magic</h1>
		<?php
$result = ini_set('default_charset', 'UTF-8');
ini_set('log_errors', false);
ini_set('display_errors','1');
ini_set('display_startup_errors','1');


			//include class file
			require("MyStructMagic.class.php");
			
		?>
		<form method="post" action="#result">
			<input type="hidden" name="action" value="exportXml" />
			<fieldset>
				<legend>Export database to XML</legend>
				<label>Filename * : </label><input type="text" name="filename" value="<?php echo $mysql_database.'_'.date("Ymd").'.xml'; ?>" /><br />
				<input type="submit" value="Export" /><br />
				<sub>* Leave empty to output the database XML</sub>
			</fieldset>
		</form>
		<br />
		<form method="post" action="#result">
			<input type="hidden" name="action" value="exportPhp" />
			<fieldset>
				<legend>Export database to Php</legend>
				<label>Var Name * : </label><input type="text" name="varname" value="<?php echo $mysql_database; ?>" /><br />
				<label>Filename ** : </label><input type="text" name="filename" value="<?php echo $mysql_database.'_'.date("Ymd").'.php'; ?>" /><br />
				<input type="submit" value="Export" /><br />
				<sub>* If empty, defaults to 'database'</sub><br />
				<sub>** Leave empty to output the database php code</sub>
			</fieldset>
		</form>
		<br />
		<form method="post" action="#result">
			<input type="hidden" name="action" value="getDiffSql" />
			<fieldset>
				<legend>Get difference sql from exported to current</legend>
				<label>Var Name * : </label><input type="text" name="varname" value="<?php echo $mysql_database; ?>" /><br />
				<label>Filename ** : </label><input type="text" name="filename" /><br />
				<input type="submit" value="Show" /><br />
				<sub>* Var name used with php export, empty to let program handle it</sub><br />
				<sub>** File where export was made</sub>
			</fieldset>
		</form>
		<br />
		<form method="post" action="#result">
			<fieldset id="result">
				<legend>Result of selected operation</legend>
				<pre>
				<?php
					//init object
					$dbStructMagic = new mySqlStructMagic();
					
					echo "Active database : $mysql_database  \n\n";
					
					switch($_POST['action']) {
						case 'exportXml':
							echo "ExportXml : \n";
							$filename = ( empty($_POST['filename']) ) ? false : $_POST['filename'] ;
							$status = $dbStructMagic->exportXtml($filename);
							print_r( ( $status === true ) ? "Operation successfull, saved to $filename" : htmlspecialchars($status) );
						break;
						case 'exportPhp':
							echo "ExportPhp : \n";
							$filename = ( empty($_POST['filename']) ) ? false : $_POST['filename'] ;
							$varname = ( empty($_POST['varname']) ) ? false : $_POST['varname'] ;
							$status = $dbStructMagic->exportPhp($varname, $filename);
							print_r( ( $status === true ) ? "Operation successfull, saved to $filename" : htmlspecialchars($status) );
						break;
						case 'getDiffSql':
							echo "Get Difference Sql : \n";
							$filename = ( empty($_POST['filename']) ) ? false : $_POST['filename'] ;
							$varname = ( empty($_POST['varname']) ) ? false : $_POST['varname'] ;
							$dbStructMagic->importPhp($varname,$filename);
							$diff = $dbStructMagic->getDiffSql();
							if( empty($diff) ) {
								echo "Database up to date with source";
							} else {
								$iCnt=1;
								foreach($diff as $label=>$command) {
									?><br /><label><?php echo $label; ?></label><br /><?php if(!empty($command)){ ?><textarea rows="5" cols="125"  name="queries[<?php echo $iCnt; ?>]"><?php echo $command; ?></textarea><br /><input type="checkbox" name="selects[<?php echo $iCnt; ?>]" value="<?php echo $iCnt; ?>" />Select<?php } ?><br /><?php
									$iCnt ++;
								}
								?><input type="hidden" name="action" value="executeSql" /><?php
								?><br /><input type="submit" value="Execute selected queries" /><?php
							}
						break;
						case 'executeSql':
							//open mysql connection
							$db = Database::getInstance();
							
							if( !is_array($_POST['selects']) ) {
								$_POST['selects'] = array($_POST['selects']);
							}
							foreach($_POST['selects'] as $iCnt) {
								if( empty($_POST['queries'][$iCnt]) ) {
									continue;
								}
								$status = @$db->query(stripslashes($_POST['queries'][$iCnt]));
								if(!$status) {
									echo "<br/>Failed : ".stripslashes($_POST['queries'][$iCnt]).", Reason : ".$db->error()." <br />";
								} else {
									echo "<br/>Ok : ".stripslashes($_POST['queries'][$iCnt])." <br />";
								}
							}
						break;
						default:
							echo "No operation selected";
					}
					$dbStructMagic->endClean();
				?>
				</pre>
			</fieldset>
		</form>
		<p>
			<a href="http://bigpress.net/">BigPress Software</a>
		</p>
	</body>
</html>