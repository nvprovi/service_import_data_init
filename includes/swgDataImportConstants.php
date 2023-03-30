<?php
$schulferien_he = array(
    'winter_start' => array(1546297200,1547333999),
    'ostern' => array(1555279200,1556402399),
    'sommer' => array(1561932000,1565387999),
    'herbst' => array(1569794400,1570917599),
    'winter_end' => array(1577055600,1577833199),
);
$todayStart = getDayStart(time());


$hstInfos = getHstInfos();
foreach($hstInfos as $sfpID => $hstName){
    $hstInfos[$sfpID] = utf8_encode($hstName);
}
