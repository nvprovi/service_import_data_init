<?php
date_default_timezone_set("Europe/Berlin");
include('includes/swgaDatabase.php');
include('includes/swgDataImportFunctions.php');
include('includes/swgDataImportConstants.php');


$dataDirectory = "";
$fileName = "";
if($fileName == ''){echo"no file specified";exit();}

convert_file_to_utf8($dataDirectory.$fileName);

$dataFileHandler = fopen($dataDirectory.$fileName,"r");  
$skipFirstLine = FALSE;
$headlines = array();
$lineCount = 1;
$insertsPerStatement = 100;
$mergeSQLStatement = "";
$belegCount = 1;
while(!feof($dataFileHandler))  {
    $line = fgets($dataFileHandler);    
    $line = str_replace('""', "'",$line);
    $line = str_replace('"','',$line);
    //$lineArray = str_getcsv($line,$delimiter="\t",$enclosure="'");
    $lineArray = str_getcsv($line,$delimiter=";",$enclosure="'");

    
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

    $row['Linie'] = str_replace("N Venus","910",$row['Linie']); // ex 901
    $row['Linie'] = str_replace("N Saturn","920",$row['Linie']); // ex 902
    $row['Linie'] = intval($row['Linie']);   

    $row['verspaetungSek'] = getSecondsFromTimeString($row['Fahrplanabweichung']);
    $row['unixtimeDayStart'] = getDayStart(strtotime($row['Datum']));    
    $row['sollabfahrtSeconds'] = getSecondsFromTimeString($row['SollAbfahrt']);   
    $row['istzeitSeconds'] = getSecondsFromTimeString($row['Istzeit']);   
    $row['istHaltedauer'] = getSecondsFromTimeString($row['IstHaltedauer']);  
    $row['abfahrtAnHSTSeconds'] = $row['istzeitSeconds'] - $row['verspaetungSek'];  
    $row['unixtime'] = $row['unixtimeDayStart'] + $row['istzeitSeconds'];
    if($row['unixtime'] <= 0){print_r($row);}    
    $row['tripID'] = $row['unixtimeDayStart'].":".$row['sollabfahrtSeconds'].":".$row['Fahrt'].":".$row['Linie'];
    $row['wochentag'] = date("N",$row['unixtimeDayStart']);     
    $row['feiertag'] = isFeiertag(date("j",$row['unixtime']), date("n",$row['unixtime']), date("Y",$row['unixtime']), "he");    
    $row['ferien'] = "0";
    foreach($schulferien_he as $type => $timeRange){
        if(isBetween($row['unixtime'],$timeRange[0],$timeRange[1])){$row['ferien'] = $type;}
    }


    /*
    if($row['Belegung'] > 70){echo $belegCount." : ".$row['tripID']."\n";$belegCount++;}
    continue;
    */

    
    // manual adjustments to name
    $row['hstFull'] = $row['Haltestelle'];
    if($row['hstFull'] == 'Kirche,1'){$row['hstFull'] = "Kirche Lützellinden,1";}    
    if($row['hstFull'] == 'Friedhof,1' AND $row['Linie']=='5'){$row['hstFull'] = "Friedhof Wieseck,1";}
    if($row['hstFull'] == 'Friedhof,2' AND $row['Linie']=='5'){$row['hstFull'] = "Friedhof Wieseck,2";}

    $row['hstName'] = substr($row['hstFull'],0,-2);
    $row['hstSteig'] = substr($row['hstFull'],-1);
    $row['hstSFPid'] = "";  
    
    if(in_array($row['hstName'],$hstInfos)){
        $keys = array_keys($hstInfos,$row['hstName']);
        $row['hstSFPid'] = $keys[0];
    }



    $mergeSQLStatement .= "
        INSERT INTO `clientdata_swg_main`(
                Linie,hstFull,hstName,hstSFPid,hstSteig,sollabfahrtSeconds,abfahrtAnHSTSeconds,tripID,istzeitSeconds,verspaetungSek,istHaltedauer,
                unixtime,unixtimeDayStart,wochentag,feiertag,ferien,
                fahrzeug,fzTyp,fahrer,kapazitaet,einstiege,ausstiege,belegung,wum,fahrt
            ) VALUES (
            '".$row['Linie']."',
            '".utf8_decode($row['hstFull'])."',
            '".utf8_decode($row['hstName'])."',
            '".$row['hstSFPid']."',
            '".$row['hstSteig']."',
            '".$row['sollabfahrtSeconds']."',
            '".$row['abfahrtAnHSTSeconds']."',
            '".$row['tripID']."',
            '".$row['istzeitSeconds']."',
            '".$row['verspaetungSek']."',
            '".$row['istHaltedauer']."',
            '".$row['unixtime']."',
            '".$row['unixtimeDayStart']."',
            '".$row['wochentag']."',
            '".$row['feiertag']."',
            '".$row['ferien']."',
            '".$row['Fahrzeug']."',
            '".$row['Fahrzeugtyp']."',
            '".$row['Fahrer']."',
            '".$row['Kapazitaet']."',
            '".$row['EinGesamt']."',
            '".$row['AusGesamt']."',
            '".$row['Belegung']."',
            '".$row['Umlauf']."',
            '".$row['Fahrt']."'
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
        $mergeSQLStatement = "";
        echo $lineCount."\r\n";
    }
    
    $lineCount++;
}

if ($mysqli->multi_query($mergeSQLStatement)) {
    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }

    } while ($mysqli->more_results() && $mysqli->next_result());
}               
echo $lineCount."\n";
fclose($dataFileHandler);



