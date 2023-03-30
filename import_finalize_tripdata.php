<?php
date_default_timezone_set("Europe/Berlin");
include('includes/swgaDatabase.php');
include('includes/swgDataImportFunctions.php');
include('includes/swgDataImportConstants.php');

$all_trips_without_direction = cronJob_getAllTripIDs_without_direction();
foreach($all_trips_without_direction as $tripID){  
  cronJob_setDirectionForRoute($tripID);   
  echo $tripID."\n";
}
exit();
updateTripInformation();
updateTripStartHst();