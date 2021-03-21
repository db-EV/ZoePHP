<?php
session_cache_limiter('nocache');
require 'api-keys.php';
require 'config.php';
if (file_exists('lng/'.$country.'.php')) require 'lng/'.$country.'.php';
else require 'lng/EN.php';
header('Content-Type: text/html; charset=utf-8');
if (empty(${$country})) $gigya_api = $GB;
else $gigya_api = ${$country};

//Request cached login
$session = file_get_contents('session');
$session = explode('|', $session);

//Request charging history
$postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session[1]
);
$ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/charges?country='.$country.'&start='.date("Ymd", strtotime("-1 months")).'&end='.date("Ymd"));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
$response = curl_exec($ch);
if ($response === FALSE) die(curl_error($ch));
$responseData = json_decode($response, TRUE);

//Output
echo '<HTML>'."\n".'<HEAD>'."\n".'<LINK REL="stylesheet" HREF="stylesheet.css">'."\n".'<META NAME="viewport" CONTENT="width=device-width, initial-scale=1.0">'."\n".'<TITLE>'.$zoename.'</TITLE>'."\n".'</HEAD>'."\n".'<BODY>'."\n".'<DIV ID="container">'."\n".'<MAIN>'."\n".'<ARTICLE>'."\n".'<TABLE>'."\n".'<TR ALIGN="left"><TH>'.$zoename.'</TH></TR>'."\n".'<TR><TD COLSPAN="2"><HR></TD></TR>'."\n";
for ($i = 0; $i < count($responseData['data']['attributes']['charges']); $i++) {
  if (!empty($responseData['data']['attributes']['charges'][$i]['chargeStartDate'])) {
    $s = date_create_from_format(DATE_ISO8601, $responseData['data']['attributes']['charges'][$i]['chargeStartDate'], timezone_open('UTC'));
    $s = date_timezone_set($s, timezone_open('Europe/Berlin'));
    $s = date_format($s, 'd.m.Y H:i');
    echo '<TR><TD>'.'Start'.':</TD><TD>'.$s.'</TD></TR>'."\n";
    echo '<TR><TD>'.'Charging'.':</TD><TD>'.$responseData['data']['attributes']['charges'][$i]['chargeStartBatteryLevel'].' % to '.$responseData['data']['attributes']['charges'][$i]['chargeEndBatteryLevel'].' % in '.$responseData['data']['attributes']['charges'][$i]['chargeDuration'].' minutes</TD></TR>'."\n";
    if ($zoeph == 1) {
	  $s = $responseData['data']['attributes']['charges'][$i]['chargeStartInstantaneousPower']/1000;
      echo '<TR><TD>'.'Power'.':</TD><TD>'.$responseData['data']['attributes']['charges'][$i]['chargePower'].' ('.$s.' kW)</TD></TR>'."\n";
    }
    $s = date_create_from_format(DATE_ISO8601, $responseData['data']['attributes']['charges'][$i]['chargeEndDate'], timezone_open('UTC'));
    $s = date_timezone_set($s, timezone_open('Europe/Berlin'));
    $s = date_format($s, 'd.m.Y H:i');
    echo '<TR><TD>'.'Status'.':</TD><TD>'.$responseData['data']['attributes']['charges'][$i]['chargeEndStatus'].' at '.$s.'</TD></TR>'."\n".'<TR><TD COLSPAN="2"><HR></TD></TR>'."\n";
  }
}
echo '<TR><TD COLSPAN="2"><A HREF="./">'.'Back'.'</A></TD></TR>'."\n".'</TABLE>'."\n".'</ARTICLE>'."\n";
echo '</MAIN>'."\n".'</DIV>'."\n".'</BODY>'."\n".'</HTML>';
curl_close($ch);
?>
