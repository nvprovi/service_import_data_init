<?php
$DBServer = ''; 
$DBName = ''; 
$DBUser = '';
$DBPass = "";
//$DBPass="!swg2018!BC";


$mysqli = new mysqli($DBServer, $DBUser, $DBPass, $DBName);
if ($mysqli->connect_errno) {echo "Failed to connect to MySQL";}
