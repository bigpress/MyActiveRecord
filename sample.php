<?php

/*

This PHP file illustrates many of the features of MyActiveRecord.
Create a blank database on your database server, and alter the database
connection string below, in order to try it out.

This script will create the necessary database tables the first time it is
run.

*/


// Include MyActiveRecord
include 'config.php';
include 'Database.class.php';
include 'MyActiveRecord.class.php';

// Create the database tables
$car_sql = "
CREATE TABLE `car`
(
  `id` int(11) NOT NULL auto_increment,
  `make` varchar(50) NOT NULL default '',
  `model` varchar(50) NOT NULL default '',
  `colour` varchar(50) default NULL,
  `driver_id` int(11) default NULL,
  PRIMARY KEY  (`id`)
)
";

$driver_sql = "
CREATE TABLE `driver`
(
  `id` int(11) NOT NULL auto_increment,
  `first` varchar(50) default NULL,
  `last` varchar(50) default NULL,
  `class` varchar(50) default NULL,
  PRIMARY KEY  (`id`)
)
";

$car_driver_sql = "
CREATE TABLE `car_driver`
(
  `car_id` int(11) default NULL,
  `driver_id` int(11) default NULL,
  KEY `car_id` (`car_id`),
  KEY `driver_id` (`driver_id`)
)
";

// create Car Table
if( !MyActiveRecord::TableExists('car') ) 
{
	MyActiveRecord::Query($car_sql);
	print "Created `car` Table\n";
}

// create Driver Table
if( !MyActiveRecord::TableExists('driver') )
{
	MyActiveRecord::Query($driver_sql);
	print "Created `driver` Table\n";
}

// create Car_Driver Table
if( !MyActiveRecord::TableExists('car_driver') )
{
	MyActiveRecord::Query($car_driver_sql);
	print "Created `car_driver` Table\n";
}

// Create mapping classes
class Car extends MyActiveRecord 
{
	function destroy()
	{
		// clean up attached People (drivers) on destroy
		foreach( $this->find_attached('Driver') as $driver ) $this->detach($driver);
		return parent::destroy();
	}	
}

class Driver extends MyActiveRecord
{
	function destroy()
	{
		// clean up attached (driven) cars on destroy
		foreach( $this->find_attached('Car') as $car ) $this->detach($car);
		return parent::destroy();
	}
}

// Single Table Inheritance
class FemaleDriver extends Driver {}
class MaleDriver extends Driver {}

// Delete any existing data from tables
foreach( MyActiveRecord::FindAll('Car') as $car ) $car->destroy();
foreach( MyActiveRecord::FindAll('Driver') as $driver ) $driver->destroy();

// Create cars
$ka = MyActiveRecord::Create('Car', array('make'=>'Ford', 'model'=>'Ka', colour=>'Silver') );
$ka->save();

$c4 = MyActiveRecord::Create('Car', array('make'=>'Citroen', 'model'=>'C4', colour=>'Silver') );
$c4->save();

// Create drivers
$jake = MyActiveRecord::Create('MaleDriver', array('first'=>'Jake', 'last'=>'Grimley') );
$jake->save();

$jana = MyActiveRecord::Create('FemaleDriver', array('first'=>'Jana', 'last'=>'Grimley') );
$jana->save();

// One-to-many relationships
$ka->driver_id = $jana->id;	// Jana owns the Ford Ka
$ka->save();

$c4->driver_id = $jake->id; // Jake owns the Citroen C4
$c4->save();

// Many-to-many relationships
// Jake is allowed to drive the C4 and the Ka
MyActiveRecord::link($c4, $jake);
$ka->attach($jake);

// Jana is allowed to drive the C4 and the Ka
$jana->set_attached('Car', array($c4->id, $ka->id));

// Display cars and drivers with relationships
print "Drivers:\n";
foreach( MyActiveRecord::FindAll('Driver') as $driver )
{
	$driver->owns = $driver->find_children('Car');
	$driver->drives  = $driver->find_attached('Car');
	print_r($driver);
}
print "Cars:\n";
foreach( MyActiveRecord::FindAll('Car') as $car )
{
	$car->owner = $car->find_parent('Driver');
	$car->drivers = $car->find_attached('Driver');
	print_r($car);
}
// show car colours
foreach( MyActiveRecord::FreqDist('Car', 'colour') as $colour=>$total )
{
	print "There are $total $colour coloured cars\n";
}
$ka->validate_uniqueness_of('colour')
	or print "The Colour of the Ford Ka is not unique\n";

// Clean up data
foreach( MyActiveRecord::FindAll('Car') as $car ) $car->destroy();
foreach( MyActiveRecord::FindAll('Driver') as $driver ) $driver->destroy();

