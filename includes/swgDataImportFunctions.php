<?php


function getDayStart($timecode){
    return mktime(0,0,0,date('n',$timecode),date('j',$timecode),date('Y',$timecode));
}

function utf8_fopen_read($fileName) { 
    $fc = iconv('windows-1250', 'utf-8', file_get_contents($fileName)); 
    $handle=fopen("php://memory", "rw"); 
    fwrite($handle, $fc); 
    fseek($handle, 0); 
    return $handle; 
} 

function getHstInfos(){  
    global $mysqli;
    $hstInfos = array();
    $result = $mysqli->query("SELECT id,hstName,bussteige FROM `data_nahverkehr_haltestellen` WHERE  client_id='swg'");
    while($row = $result->fetch_assoc()){ 
        $hstInfos[$row['id']] = $row['hstName'];
        
    }    
    return $hstInfos;  
    
}

function convert_file_to_utf8($csvfile){
    $utfcheck = file_get_contents($csvfile);
    $utfcheck = utf16_to_utf8($utfcheck);
    file_put_contents($csvfile,$utfcheck);
}

function utf16_to_utf8($str) {
    $c0 = ord($str[0]);
    $c1 = ord($str[1]);
 
    if ($c0 == 0xFE AND $c1 == 0xFF) {
        $be = true;
    } else if ($c0 == 0xFF AND $c1 == 0xFE) {
        $be = false;
    } else {
        return $str;
    }
 
    $str = substr($str, 2);
    $len = strlen($str);
    $dec = '';
    for ($i = 0; $i < $len; $i += 2) {
        $c = ($be) ? ord($str[$i]) << 8 | ord($str[$i + 1]) :
                ord($str[$i + 1]) << 8 | ord($str[$i]);
        if ($c >= 0x0001 AND $c <= 0x007F) {
            $dec .= chr($c);
        } else if ($c > 0x07FF) {
            $dec .= chr(0xE0 | (($c >> 12) & 0x0F));
            $dec .= chr(0x80 | (($c >>  6) & 0x3F));
            $dec .= chr(0x80 | (($c >>  0) & 0x3F));
        } else {
            $dec .= chr(0xC0 | (($c >>  6) & 0x1F));
            $dec .= chr(0x80 | (($c >>  0) & 0x3F));
        }
    }
    return $dec;
}
 
function getSecondsFromTimeString($timestring){
    $factor = 1;
    if(strlen($timestring) == 9){
        $factor = -1;
        $timestring = substr($timestring,1);
    }
    
    $timeArray = explode(":",$timestring);
    if(sizeof($timeArray) !== 3){return false;}
    return ($timeArray[0] * 3600 + $timeArray[1] * 60 + $timeArray[2]) * $factor;
}

function isBetween($val, $min, $max){    
    if($val >= $min && $val <= $max){return true;}
    return false;
}

function isFeiertag($tag, $monat, $jahr, $land) {
    global $setMariaHimmelfahrtFeiertag;

    // Parameter in richtiges Format bringen
    if(strlen($tag) == 1) {$tag = "0".$tag; }
    if(strlen($monat) == 1) {$monat = "0".$monat;}


    // Feste Feiertage werden nach dem Schema ddmm eingetragen
    $feiertage[] = "0101"; // Neujahrstag
    $feiertage[] = "0105"; // Tag der Arbeit
    $feiertage[] = "0310"; // Tag der Deutschen Einheit
    $feiertage[] = "2512"; // Erster Weihnachtstag
    $feiertage[] = "2612"; // Zweiter Weihnachtstag

    // Bewegliche Feiertage berechnen
    $tage = 60 * 60 * 24;
    $ostersonntag = easter_date($jahr);
    $feiertage[] = date("dm", $ostersonntag - 2 * $tage);  // Karfreitag
    $feiertage[] = date("dm", $ostersonntag + 1 * $tage);  // Ostermontag
    $feiertage[] = date("dm", $ostersonntag + 39 * $tage); // Himmelfahrt
    $feiertage[] = date("dm", $ostersonntag + 50 * $tage); // Pfingstmontag

    //landesspezifische Feiertage
    // F3 Könige
    if(in_array($land,array("bw","by","sa"))){
        $feiertage[] = "0601"; // Hl. 3 Kï¿œnige
    }
    //Fronleichnahm
    if(in_array($land,array("bw","by","he","nrw","rp","sl"))){
        $feiertage[] = date("dm", $ostersonntag + 60 * $tage); 
    }
  
    // Reformationstag
    if(in_array($land,array("bra","mv","s","sa","th"))){
        $feiertage[] = "3110"; 
    }
     // Allerheiligen        
    if(in_array($land,array("bw","by","nrw","rp","sl"))){
        $feiertage[] = "0111"; 
    }
    
    //reformationstag 2017
    if($jahr == 2017){
        $feiertage[] = "3110";
    }

    // Prüfen, ob Feiertag
    $code = $tag.$monat;
    if(in_array($code, $feiertage)) {
        return true;
    }
    else {
        return false;
    }
}

function updateTripInformation(){
  global $mysqli;
  $allTripIDs = cronJob_getAllTripIDs();
  $flippedTipsIDs = array_flip($allTripIDs);

  //update trip Information    
  $result = $mysqli->query("SELECT DISTINCT tripID FROM `clientdata_swg_main` WHERE 1");
  while($row = $result->fetch_assoc()){        
    $tripID = $row['tripID'];
    if(isset($flippedTipsIDs[$tripID])){continue;} // faster than in_array
    cronJob_setTripInfosForNewData($tripID);
    cronJob_setDirectionForRoute($tripID);    
  }  
}

function cronJob_getAllTripIDs(){ 
  global $mysqli;           
 
  $tripIDs = array();
  $result = $mysqli->query("SELECT tripID FROM  `clientdata_swg_tripInfos` ORDER BY unixtimeDayStart ASC");
  while($row = $result->fetch_assoc()){
      $tripIDs[] = $row['tripID'];
  }    
  return $tripIDs;    
}

function cronJob_getAllTripIDs_without_direction(){ 
  global $mysqli;           
 
  $tripIDs = array();
  $result = $mysqli->query("SELECT tripID FROM  `clientdata_swg_tripInfos` WHERE direction = '' ORDER BY unixtimeDayStart ASC");
  while($row = $result->fetch_assoc()){
      $tripIDs[] = $row['tripID'];
  }    
  return $tripIDs;    
}

function cronJob_setTripInfosForNewData($tripID){
  global $mysqli;
  $isBelegungTrip = 0;
  if(cronJob_checkIfBelegungDataForTrip($tripID)){$isBelegungTrip = 1;}
  $resultInner = $mysqli->query("SELECT * FROM `clientdata_swg_main` WHERE tripID='".$tripID."' LIMIT 1");
  $rowInner = $resultInner->fetch_assoc();
  $rowInner['fahrplanJahr'] = date("Y",$rowInner['unixtimeDayStart']);   
  $mysqli->query("INSERT INTO `clientdata_swg_tripInfos`
      (`tripID`, `Linie`, `wochentag`, `feiertag`, `ferien`, `unixtimeDayStart`, `sollabfahrtSeconds`,`fahrplanJahr`, `fahrzeug`, `fzTyp`, 
      `fahrer`, `kapazitaet`, `wum`, `fahrt`, `isBelegungFahrt`) VALUES 
      (
          '".$rowInner['tripID']."',
          '".$rowInner['Linie']."',
          '".$rowInner['wochentag']."',
          '".$rowInner['feiertag']."',
          '".$rowInner['ferien']."',
          '".$rowInner['unixtimeDayStart']."',
          '".$rowInner['sollabfahrtSeconds']."',
          '".$rowInner['fahrplanJahr']."',
          '".$rowInner['fahrzeug']."',
          '".$rowInner['fzTyp']."',
          '".$rowInner['fahrer']."',
          '".$rowInner['kapazitaet']."',
          '".$rowInner['wum']."',
          '".$rowInner['fahrt']."',
          ".$isBelegungTrip."
      )
  ");
}

function cronJob_checkIfBelegungDataForTrip($tripID){
  global $mysqli;   
  $zaehlfahrzeuge = array(11,12,13,14,15,16,17,18,19,20,46,47,54,55,56,57);
  $zaehlfahrzeuge = array(5,6,9,10,11,12,13,14,15,16,17,18,19,20,24,25,27,28,45,46,47,54,55,56,57);
  $result = $mysqli->query("SELECT fahrzeug FROM `clientdata_swg_main` WHERE tripID='$tripID' ORDER BY unixtime ASC LIMIT 1");
  $row = $result->fetch_assoc();
  if(isset($row['fahrzeug']) AND in_array($row['fahrzeug'],$zaehlfahrzeuge)){return true;}
  return false; 
}

function cronJob_setDirectionForRoute($tripID){
  global $mysqli;
  $tripInfos = cronJob_getInfosForTrip($tripID);
  $lineInfos = cronJob_getSingleLineRouteByHstID($tripInfos['generalInfo']['Linie']);
  $compareArray = array_keys(array_slice($tripInfos['route'], 0, 2));
  $thisDirection = FALSE;
  $compareIndexStart = 0;
  while(!$thisDirection AND $compareIndexStart < sizeof($tripInfos['route'])-3){
      $compareArray = array_keys(array_slice($tripInfos['route'], $compareIndexStart, 2));
      foreach($lineInfos as $direction => $stopArray){
          $intersect = array_values(array_intersect($stopArray,$compareArray));                
          if($intersect === $compareArray){$thisDirection = $direction;} 
      }
      $compareIndexStart++;
  }   
  if($tripInfos['generalInfo']['Linie'] == 18){
    $thisDirection = ($tripInfos['thisTripStartHST'] == 'N92KRSXD') ? "LN9PEQ4K" : "N92KRSXD";
  }
  if($thisDirection){
      $mysqli->query("UPDATE `clientdata_swg_tripInfos` SET direction='$thisDirection' WHERE tripID='$tripID'");
  }
  return array(
      'thisDirection' => $thisDirection,
      'compareIndex' => $compareIndexStart,
      'lineInfos' => $lineInfos,
      'tripInfos' => $tripInfos,
  );
}

function cronJob_getInfosForTrip($tripID,$returnRoute = TRUE){
  global $mysqli;   
  $tripInfos = array();
  $result = $mysqli->query("SELECT Linie,direction,thisTripStartHST,wochentag,feiertag,ferien,unixtimeDayStart,sollabfahrtSeconds,kapazitaet,isBelegungFahrt,tripID FROM  `clientdata_swg_tripInfos` WHERE tripID='$tripID'");
  $row = $result->fetch_assoc();
  $tripInfos['generalInfo'] = $row;
  if($returnRoute){
      $tripInfos['route'] = array();
      $result = $mysqli->query("SELECT unixtime,hstName,hstSteig,hstSFPid,istzeitSeconds,verspaetungSek,einstiege,ausstiege,belegung FROM  `clientdata_swg_main` WHERE tripID='$tripID' ORDER BY unixtime ASC");
      while($row = $result->fetch_assoc()){
          $tripInfos['route'][$row['hstSFPid']] = $row;
      }
  }
  return $tripInfos;
}

function cronJob_getSingleLineRouteByHstID($line){
  global $mysqli;  
  $routes = array();
  $result = $mysqli->query("SELECT * FROM  `data_nahverkehr_lineRoutes` WHERE line='$line' AND client_id='swg' AND fahrplanjahr='2023' ORDER BY stopnumber ASC");
  while($row = $result->fetch_assoc()){         
      $routes[$row['direction']][$row['stopnumber']] = $row['hst'];
  }    
  return $routes;      
}

function updateTripStartHst(){
  global $mysqli;
  /* insert starthaltestelle für Trips */    
  $tripsWithoutStartHst = array();
  $result = $mysqli->query("SELECT tripID FROM `clientdata_swg_tripInfos` WHERE thisTripStartHST=''");
  while($row = $result->fetch_assoc()){    
      $tripsWithoutStartHst[] = $row['tripID'];
  }
  $startHst = array();
  foreach($tripsWithoutStartHst as $tripID){
    $innerResult = $mysqli->query("SELECT hstSFPid FROM `clientdata_swg_main` WHERE tripID='".$tripID."' ORDER BY unixtime ASC LIMIT 1");
    $rowInner = $innerResult->fetch_assoc();
    $startHst[$tripID] = $rowInner['hstSFPid'];
  }
  foreach($startHst as $tripID => $startHST){
    $mysqli->query("UPDATE `clientdata_swg_tripInfos` SET thisTripStartHST='".$startHST."' WHERE tripID='".$tripID."'");        
  }
}