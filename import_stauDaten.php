<?php
date_default_timezone_set("Europe/Berlin");
include('includes/swgaDatabase.php');
include('includes/swgDataImportFunctions.php');
include('includes/swgDataImportConstants.php');

$dataDirectory = "";
$fileName = "";
if($fileName == ''){echo"no file specified";exit();}

//size 632251
//2018: 88748
//aug: 134469
// sep 434930 (+4 072 315)
convert_file_to_utf8($dataDirectory.$fileName);

$dataFileHandler = fopen($dataDirectory.$fileName,"r");  
$skipFirstLine = FALSE;
$headlines = array();
$lineCount = 1;
$insertsPerStatement = 100;
$mergeSQLStatement = "";

while(!feof($dataFileHandler))  {    
    $line = fgets($dataFileHandler);    
    $line = str_replace('""', "'",$line);
    $line = str_replace('"','',$line);
    $lineArray = str_getcsv($line,$delimiter="\t",$enclosure="'");

    if(!$skipFirstLine){
        $skipFirstLine = TRUE;
        $headlines = $lineArray;
        foreach($headlines as $index => $name){
            $headlines[$index] = str_replace(array(" ","ä",".","-"),array("","ae","",""),$name);            
        }          
        continue;         
    }
    


    if(sizeof($lineArray) !== sizeof($headlines)){continue;}
    $row = array();
    foreach($lineArray as $index => $value){
        $row[$headlines[$index]] = $value;
    }

    
    $row['Linie'] = str_replace("[V:0000]","",$row['Linie']);
    $row['Linie'] = str_replace("[V:0001]","",$row['Linie']);
    $row['Linie'] = str_replace("[V:0002]","",$row['Linie']);          
    $row['Linie'] = str_replace("[V:0003]","",$row['Linie']);            
    $row['Linie'] = str_replace("[V:0004]","",$row['Linie']);            
    $row['Linie'] = str_replace("[V:0005]","",$row['Linie']);            
    $row['Linie'] = str_replace("[V:0006]","",$row['Linie']);            
    $row['Linie'] = str_replace("[V:0007]","",$row['Linie']);            
    $row['Linie'] = str_replace("[V:0008]","",$row['Linie']);            
    $row['Linie'] = preg_replace("/\[[^)]+\]/","",$row['Linie']);

    $row['Linie'] = str_replace("N Venus","901",$row['Linie']);
    $row['Linie'] = str_replace("N Saturn","902",$row['Linie']);
    $row['Linie'] = intval($row['Linie']);    
    $row['unixtimeDayStart'] = getDayStart(strtotime($row['Datum']));    
    $row['weekday'] = date("N",$row['unixtimeDayStart']);     
    $row['uhrzeit'] = getSecondsFromTimeString($row['Uhrzeit']);   
    $row['unixtime'] = $row['unixtimeDayStart'] + $row['uhrzeit'];
    $row['haltedauerSecs'] = getSecondsFromTimeString($row['IstHaltedauer']);   
    $row['long'] = str_replace(",",".",$row['Laenge']);  
    $row['lat'] = str_replace(",",".",$row['Breite']);      
    
    /*
    $row['Hst'] = str_replace(array('á','„','”'),array('','',''),$row['Hst']);
    $row['hstName'] = substr($row['Hst'],0,-2);
    $row['hstSteig'] = substr($row['Hst'],-1);
    $row['hstSFPid'] = "";  
    
    if(in_array($row['hstName'],$hstInfos)){
        $keys = array_keys($hstInfos,$row['hstName']);
        $row['hstSFPid'] = $keys[0];
    }
    */
    $mergeSQLStatement .= "
        INSERT INTO `data_nahverkehr_stauzeiten` (unixtime,linie,lat,lng,haltedauerSecs,unixtimeDayStart,uhrzeit,weekday) VALUES (
            '".$row['unixtime']."',
            '".$row['Linie']."',
            '".$row['lat']."',
            '".$row['long']."',            
            '".$row['haltedauerSecs']."',
            '".$row['unixtimeDayStart']."',
            '".$row['uhrzeit']."',
            '".$row['weekday']."'
        );
    ";
    if($lineCount % $insertsPerStatement == 0){
   
        if ($mysqli->multi_query($mergeSQLStatement)) {
            do {
                if ($result = $mysqli->store_result()) {
                    $result->free();
                }
        
            } while ($mysqli->more_results() && $mysqli->next_result());
        }           
        print_r($mysqli->error)  ;
        $mergeSQLStatement = "";
        echo $lineCount."\n";
    }    
    $lineCount++;
}
//insert last data
if ($mysqli->multi_query($mergeSQLStatement)) {
    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }

    } while ($mysqli->more_results() && $mysqli->next_result());
}               
$mergeSQLStatement = "";
echo $lineCount."\n";

fclose($dataFileHandler);