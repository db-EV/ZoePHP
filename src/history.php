<?php
session_cache_limiter('nocache');
require 'api-keys.php';
require 'config.php';
if (file_exists('lng/'.$country.'.php')) require 'lng/'.$country.'.php';
else require 'lng/EN.php';
header('Content-Type: text/html; charset=utf-8');
if (empty(${$country})) $gigya_api = $GB;
else $gigya_api = ${$country};

$date_today = date_create('now');
$date_today = date_format($date_today, 'md');
$update_ok = FALSE;

//Request cached login
$session = file_get_contents('session');
$session = explode('|', $session);

//Retrieve new Gigya token if the session file is outdated
if ($session[0] !== $date_today) {
  //Login Gigya
  $update_ok = TRUE;
  $postData = array(
    'ApiKey' => $gigya_api,
    'loginId' => $username,
    'password' => $password,
    'include' => 'data',
    'sessionExpiration' => 60
  );
  $ch = curl_init('https://accounts.eu1.gigya.com/accounts.login');
  curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));  
  $responseData = json_decode($response, TRUE);
  $oauth_token = $responseData['sessionInfo']['cookieValue'];

  //Request Gigya JWT token
  $postData = array(
    'login_token' => $oauth_token,
    'ApiKey' => $gigya_api,
    'fields' => 'data.personId,data.gigyaDataCenter',
    'expiration' => 87000
  );
  $ch = curl_init('https://accounts.eu1.gigya.com/accounts.getJWT');
  curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
  $responseData = json_decode($response, TRUE);
  $session[1] = $responseData['id_token'];
  $session[0] = $date_today;
}

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
$data = array();
if (isset($responseData['data']['attributes']['charges'])) $data = $responseData['data']['attributes']['charges'];
usort($data, function($a, $b) {
    return $b['chargeStartDate'] <=> $a['chargeStartDate'];
});

//Output
echo '<HTML>'."\n".'<HEAD>'."\n".'<LINK REL="stylesheet" HREF="stylesheet.css">'."\n".'<META NAME="viewport" CONTENT="width=device-width, initial-scale=1.0">'."\n".'<TITLE>'.$zoename.'</TITLE>'."\n".'</HEAD>'."\n".'<BODY>'."\n".'<DIV ID="container">'."\n".'<MAIN>'."\n".'<ARTICLE>'."\n".'<TABLE>'."\n".'<TR ALIGN="left"><TH>'.$zoename.'</TH></TR>'."\n".'<TR><TD COLSPAN="2"><HR></TD></TR>'."\n";
for ($i = 0; $i < count($data); $i++) {
  if (!empty($data[$i]['chargeStartDate']) && !empty($data[$i]['chargeEndDate']) && !empty($data[$i]['chargeEnergyRecovered'])) {
    $s = date_create_from_format(DATE_ISO8601, $data[$i]['chargeStartDate'], timezone_open('UTC'));
    $s = date_timezone_set($s, timezone_open('Europe/Berlin'));
    $sts = $s;
    $sd = date_format($s, 'd.m.Y');
    $st = date_format($s, 'H:i');
    $s = date_create_from_format(DATE_ISO8601, $data[$i]['chargeEndDate'], timezone_open('UTC'));
    $s = date_timezone_set($s, timezone_open('Europe/Berlin'));
    $ets = $s;
    $ed = date_format($s, 'd.m.Y');
    $et = date_format($s, 'H:i');
    $cd = date_diff($sts, $ets);
    $cdm = date_interval_format($cd, '%a') * 24 * 60;
    $cdm += date_interval_format($cd, '%h') * 60;
    $cdm += date_interval_format($cd, '%i');
    echo '<TR><TD>'.$lng['Start'].':</TD><TD>'.$sd.' '.$st.'</TD></TR>'."\n";
    echo '<TR><TD>'.$lng['Charging'].':</TD><TD>'.round($data[$i]['chargeEnergyRecovered'], 2).' kWh '.$lng['in'].' '.$cdm.' '.$lng['minutes'].'</TD></TR>'."\n";
    if ($cdm != 0) echo '<TR><TD>'.$lng['AverageChargingPower'].':</TD><TD>'.round($data[$i]['chargeEnergyRecovered'] * 60 / ($cdm+0.0000001), 2).' kW</TD></TR>'."\n";
    echo '<TR><TD>'.$lng['Status'].':</TD><TD>'.$data[$i]['chargeEndStatus'].' '.$lng['at'].' '.$ed.' '.$et.'</TD></TR>'."\n".'<TR><TD COLSPAN="2"><HR></TD></TR>'."\n";
  }
}
echo '<TR><TD COLSPAN="2"><A HREF="./">'.$lng['Back'].'</A></TD></TR>'."\n".'</TABLE>'."\n".'</ARTICLE>'."\n";
echo '</MAIN>'."\n".'</DIV>'."\n".'</BODY>'."\n".'</HTML>';
curl_close($ch);

//Cache new Gigya token
if ($update_ok === TRUE) {
  $session = implode('|', $session);
  file_put_contents('session', $session);
}
?>
