<?php
session_cache_limiter('nocache');
require 'api-keys.php';
require 'config.php';
if (file_exists('lng/'.$country.'.php')) require 'lng/'.$country.'.php';
else require 'lng/EN.php';
if (empty(${$country})) $gigya_api = $GB;
else $gigya_api = ${$country};

//Evaluate parameters
if (isset($_GET['cron']) || (isset($argv[1]) && $argv[1] == 'cron')) {
  header('Content-Type: text/plain; charset=utf-8');
  $cmd_cron = TRUE;
} else {
  header('Content-Type: text/html; charset=utf-8');
  $cmd_cron = FALSE;
}
if (isset($_GET['acnow']) || (isset($argv[1]) && $argv[1] == 'acnow')) $cmd_acnow = TRUE;
else $cmd_acnow = FALSE;
if (isset($_GET['chargenow']) || (isset($argv[1]) && $argv[1] == 'chargenow')) $cmd_chargenow = TRUE;
else $cmd_chargenow = FALSE;
if (isset($_GET['cmon']) || (isset($argv[1]) && $argv[1] == 'cmon')) $cmd_cmon = TRUE;
else {
  $cmd_cmon = FALSE;
  if (isset($_GET['cmoff']) || (isset($argv[1]) && $argv[1] == 'cmoff')) $cmd_cmoff = TRUE;
  else $cmd_cmoff = FALSE;
}

$date_today = date_create('now');
$date_today = date_format($date_today, 'md');
$timestamp_now = date_create('now');
$timestamp_now = date_format($timestamp_now, 'YmdHi');

/**Retrieve cached data
 * Session array
 * Date Gigya JWT: Date Gigya JWT Token request (md)
 * Gigya JWT Token: Gigya JWT Token
 * Renault account id: Renault account id
 * MD5 hash: MD5 hash of the last data retrieval
 * Timestamp last data: Timestamp of the last data retrieval (YmdHi)
 * Action done: Action done when reaching battery level (Y/N)
 * Car charging: Car is charging (Y/N)
 * Mileage: Mileage in km
 * Date status update: Date last status update
 * Time status update: Time last status update
 * Charging status: Charging status
 * Cable status: Cable status
 * Battery level: Battery level
 * Battery temp-range: Battery temperature (Ph1) / battery capacity (Ph2)
 * Range: Range in km
 * Charging time: Charging time in minutes
 * Charging effect: Charging effect
 * Outside temp-GPSlat: Outside temperature (Ph1) / GPS-Latitude (Ph2)
 * GPSLong: GPS-Longitude (Ph2)
 * GPS date: GPS date (Ph2; d.m.Y)
 * GPS time: GPS time (Ph2; H:i)
 * Setting battery level: Setting battery level for mail function
 * Outside temp: Outside temperature (Ph2; openweathermap API)
 * Weather: Weather condition (Ph2; openweathermap API)
 * Chargemode: Chargemode
 */
$session = file_get_contents('session');
if ($session !== FALSE) $session = explode('|', $session);
else $session = array('0000', '', '', '', '202001010000', 'N', 'N', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '80','','','');

//Retrieve setting battery level for mail function
if (isset($_POST['bl']) && is_numeric($_POST['bl']) && $_POST['bl'] >= 1 && $_POST['bl'] <= 99) {
  if ($_POST['bl'] > $session['Setting battery level']) $session['Action done'] = 'N';
  $session['Setting battery level'] = $_POST['bl'];
}

//Checking cron time interval
if ($cmd_cron == TRUE) {
  $s = date_create_from_format('YmdHi', $session['Timestamp last data']);
  if ($session['Car charging (Y/N)'] == 'Y') date_add($s, date_interval_create_from_date_string($cron_acs.' minutes'));
  else date_add($s, date_interval_create_from_date_string($cron_ncs.' minutes'));
  $s = date_format($s, 'YmdHi');
  if ($timestamp_now < $s) exit('INTERVAL NOT REACHED');
}

//Max one API request per minute
$s = date_create_from_format('YmdHi', $session['Timestamp last data']);
date_add($s, date_interval_create_from_date_string('1 minutes'));
$s = date_format($s, 'YmdHi');
if ($timestamp_now < $s) $update_ok = FALSE;
else $update_ok = TRUE;

//Retrieve new Gigya token if the date has changed since last request
if (empty($session['Gigya JWT Token']) || $session['Date Gigya JWT'] !== $date_today) {
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
  $personId = $responseData['data']['personId'];
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
  $session['Gigya JWT Token'] = $responseData['id_token'];
  $session['Date Gigya JWT'] = $date_today;
}

//Request Renault account id if not cached
if (empty($session['Renault account id'])) {
  //Request Kamereon account id
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session['Gigya JWT Token'],
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/persons/'.$personId.'?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
  $responseData = json_decode($response, TRUE);
  $session['Renault account id'] = $responseData['accounts'][0]['accountId'];
}

//Evaluate parameter "acnow" for preconditioning
if ($cmd_acnow === TRUE) {
  $postData = array(
    'Content-type: application/vnd.api+json',
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session['Gigya JWT Token']
  );
  $jsonData = '{"data":{"type":"HvacStart","attributes":{"action":"start","targetTemperature":"21"}}}';
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session['Renault account id'].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/actions/hvac-start?country='.$country);
  curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
}

//Evaluate parameter "chargenow" for instant charging
if ($cmd_chargenow === TRUE) {
  $postData = array(
    'Content-type: application/vnd.api+json',
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session['Gigya JWT Token']
  );
  $jsonData = '{"data":{"type":"ChargingStart","attributes":{"action":"start"}}}';
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session['Renault account id'].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/actions/charging-start?country='.$country);
  curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
}

//Evaluate parameters "cmon" respectively "cmoff" for setting the chargemode
if ($cmd_cmon === TRUE || $cmd_cmoff === TRUE) {
  $postData = array(
    'Content-type: application/vnd.api+json',
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session['Gigya JWT Token']
  );
  if ($cmd_cmon === TRUE) $jsonData = '{"data":{"type":"ChargeMode","attributes":{"action":"schedule_mode"}}}';
  else $jsonData = '{"data":{"type":"ChargeMode","attributes":{"action":"always_charging"}}}';
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session['Renault account id'].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/actions/charge-mode?country='.$country);
  curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
}

//Request battery and charging status from Renault
if ($update_ok === TRUE) {
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session['Gigya JWT Token']
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session['Renault account id'].'/kamereon/kca/car-adapter/v2/cars/'.$vin.'/battery-status?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
  $md5 = md5($response);
  $responseData = json_decode($response, TRUE);
  $s = date_create_from_format(DATE_ISO8601, $responseData['data']['attributes']['timestamp'], timezone_open('UTC'));
  $utc_timestamp = date_timestamp_get($s);
  if (empty($s)) $update_sucess = FALSE;
  else {
    $update_sucess = TRUE;
    $weather_api_dt = date_format($s, 'U');
    $s = date_timezone_set($s, timezone_open('Europe/Berlin'));
    $session['Date status update'] = date_format($s, 'd.m.Y');
    $session['Time status update'] = date_format($s, 'H:i');
    $session['Charging status'] = $responseData['data']['attributes']['chargingStatus'];
    $session['Cable status'] = $responseData['data']['attributes']['plugStatus'];
    $session['Battery level'] = $responseData['data']['attributes']['batteryLevel'];
    if (($zoeph == 1)) $session['Battery temp-range'] = $responseData['data']['attributes']['batteryTemperature'];
    else $session['Battery temp-range'] = $responseData['data']['attributes']['batteryAvailableEnergy'];
    $session['Range'] = $responseData['data']['attributes']['batteryAutonomy'];
    $session['Charging time'] = $responseData['data']['attributes']['chargingRemainingTime'];
    $s = $responseData['data']['attributes']['chargingInstantaneousPower'];
    if ($zoeph == 1) $session['Charging effect'] = $s/1000;
    else $session['Charging effect'] = $s;
  }
} else $update_sucess = FALSE;

//Request more data from Renault if changed data since last request are expected
if (isset($md5) && $md5 != $session['MD5 hash'] && $update_sucess === TRUE) {
  //Request mileage
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session['Gigya JWT Token']
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session['Renault account id'].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/cockpit?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
  $responseData = json_decode($response, TRUE);
  $s = $responseData['data']['attributes']['totalMileage'];
  if (empty($s)) $update_sucess = FALSE;
  else $session['Mileage'] = $s;

  //Request chargemode
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session['Gigya JWT Token']
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session['Renault account id'].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/charge-mode?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
  $responseData = json_decode($response, TRUE);
  $s = $responseData['data']['attributes']['chargeMode'];
  if (empty($s)) $session['Chargemode'] = 'n/a';
  else $session['Chargemode'] = $s;

  //Request outside temperature (only Ph1)
  if ($zoeph == 1) {
    $postData = array(
      'apikey: '.$kamereon_api,
      'x-gigya-id_token: '.$session['Gigya JWT Token']
    );
    $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session['Renault account id'].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/hvac-status?country='.$country);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
    $response = curl_exec($ch);
    if ($response === FALSE) die(curl_error($ch));
    $responseData = json_decode($response, TRUE);
    $s = $responseData['data']['attributes']['externalTemperature'];
    if (empty($s) && $s != '0.0') $update_sucess = FALSE;
    else $session['Outside temp-GPSlat'] = $s;
  }

  //Request GPS position (only Ph2)
  if ($zoeph == 2) {
    $postData = array(
      'apikey: '.$kamereon_api,
      'x-gigya-id_token: '.$session['Gigya JWT Token']
    );
    $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session['Renault account id'].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/location?country='.$country);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
    $response = curl_exec($ch);
    if ($response === FALSE) die(curl_error($ch));
    $responseData = json_decode($response, TRUE);
    $s = date_create_from_format(DATE_ISO8601, $responseData['data']['attributes']['lastUpdateTime'], timezone_open('UTC'));
	if (empty($s)) $update_sucess = FALSE;
	else {
      $s = date_timezone_set($s, timezone_open('Europe/Berlin'));
	  $session['Outside temp-GPSlat'] = $responseData['data']['attributes']['gpsLatitude'];
	  $session['GPSlong'] = $responseData['data']['attributes']['gpsLongitude'];
      $session['GPS date'] = date_format($s, 'd.m.Y');
	  $session['GPS time'] = date_format($s, 'H:i');
	}
  }
  
  //Request weather data from openweathermap (only Ph2)
  if ($zoeph == 2 && $weather_api_key != '') {
	$ch = curl_init('https://api.openweathermap.org/data/2.5/onecall/timemachine?lat='.$session['Outside temp-GPSlat'].'&lon='.$session['GPSlong'].'&dt='.$weather_api_dt.'&units=metric&lang='.$weather_api_lng.'&appid='.$weather_api_key);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	$response = curl_exec($ch);
	if ($response === FALSE) die(curl_error($ch));
	$responseData = json_decode($response, TRUE);	
	$session['Outside temp'] = $responseData['current']['temp'];
	$session['Weather'] = $responseData['current']['weather']['0']['description'];
  }

  //Send mail, execute command or activate schedule mode if configured
  if ($mail_bl === 'Y' || $cmon_bl === 'Y' || !empty($exec_bl)) {
    if ($session['Battery level'] >= $session['Setting battery level'] && $session['Charging status'] == 1 && $session['Action done'] != 'Y') {
      if ($session['Charging time'] != '') $s = $session['Charging time'];
	  else $s = $lng['some'];
      $sendmessage = $lng['Specified battery level reached.']."\n".$lng['Battery level'].': '.$session['Battery level'].' %'."\n".$lng['Remaining charging time'].': '.$s.' '.$lng['minutes']."\n".$lng['Range'].': '.$session['Range'].' km'."\n".$lng['Status update'].': '.$session['Date status update'].' '.$session['Time status update'];
	  if ($mail_bl === 'Y') mail($username, $zoename, $sendmessage);
	  if ($cmon_bl === 'Y') {
	    $postData = array(
	      'Content-type: application/vnd.api+json',
	      'apikey: '.$kamereon_api,
	      'x-gigya-id_token: '.$session['Gigya JWT Token']
	    );
	    $jsonData = '{"data":{"type":"ChargeMode","attributes":{"action":"schedule_mode"}}}';
	    $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session['Renault account id'].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/actions/charge-mode?country='.$country);
	    curl_setopt($ch, CURLOPT_POST, TRUE);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	    curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
	    $response = curl_exec($ch);
	    if ($response === FALSE) die(curl_error($ch));
	  }
      if (!empty($exec_bl)) shell_exec($exec_bl.' "'.$sendmessage.'"');
	  $session['Action done'] = 'Y';
    } else if ($session['Action done'] == 'Y' && $session['Charging status'] != 1) $session['Action done'] = 'N';
  }
  if ($mail_csf === 'Y' || !empty($exec_csf)) {
    $sendmessage = $lng['Charging finished.']."\n".$lng['Battery level'].': '.$session['Battery level'].' %'."\n".$lng['Range'].': '.$session['Range'].' km'."\n".$lng['Status update'].': '.$session['Date status update'].' '.$session['Time status update'];
    if ($session['Car charging (Y/N)'] == 'Y' && $session['Charging status'] != 1) {
	  if ($mail_csf === 'Y') mail($username, $zoename, $sendmessage);
      if (!empty($exec_csf)) shell_exec($exec_bl.' "'.$sendmessage.'"');
	}
	if ($session['Charging status'] == 1) $session['Car charging (Y/N)'] = 'Y';
    else $session['Car charging (Y/N)'] = 'N';
  }

  //Save data in database if configured
  if ($update_sucess === TRUE && $save_in_db === 'Y') {
    if (!file_exists('database.csv')) {
	  if ($zoeph == 1) file_put_contents('database.csv', 'Date;Time;Mileage;Outside temperature;Battery temperature;Battery level;Range;Cable status;Charging status;Charging speed;Remaining charging time;Charging schedule'."\n");
      else file_put_contents('database.csv', 'Date;Time;Mileage;Battery level;Battery capacity;Range;Cable status;Charging status;Charging speed;Remaining charging time;GPS Latitude;GPS Longitude;GPS date;GPS time;Outside temperature;Weather condition;Charging schedule'."\n");
    }
    if ($zoeph == 1) file_put_contents('database.csv', $session['Date status update'].';'.$session['Time status update'].';'.$session['Mileage'].';'.$session['Outside temp-GPSlat'].';'.$session['Battery temp-range'].';'.$session['Battery level'].';'.$session['Range'].';'.$session['Cable status'].';'.$session['Charging status'].';'.$session['Charging effect'].';'.$session['Charging time'].';'.$session['Chargemode']."\n", FILE_APPEND);
	else file_put_contents('database.csv', $session['Date status update'].';'.$session['Time status update'].';'.$session['Mileage'].';'.$session['Battery level'].';'.$session['Battery temp-range'].';'.$session['Range'].';'.$session['Cable status'].';'.$session['Charging status'].';'.$session['Charging effect'].';'.$session['Charging time'].';'.$session['Outside temp-GPSlat'].';'.$session['GPSlong'].';'.$session['GPS date'].';'.$session['GPS time'].';'.$session['Outside temp'].';'.$session['Weather'].';'.$session['Chargemode']."\n", FILE_APPEND);
  }

  //Send data to ABRP if configured
  if (!empty($abrp_token) && !empty($abrp_model)) {
    if ($session['Charging status'] == 1) $abrp_is_charging = 1;
    else $abrp_is_charging = 0;
    $jsonData = urlencode('{"car_model":"'.$abrp_model.'","utc":'.$utc_timestamp.',"soc":'.$session['Battery level'].',"odometer":'.$session['Mileage'].',"is_charging":'.$abrp_is_charging.'}');
    $ch = curl_init('https://api.iternio.com/1/tlm/send?api_key=fd99255b-91a0-45cd-9df5-d6baa8e50ef8&token='.$abrp_token.'&tlm='.$jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $response = curl_exec($ch);
    if ($response === FALSE) die(curl_error($ch));
  }
}
if (isset($ch)) curl_close($ch);

//Output
if ($cmd_cron === TRUE) {
  if ($cmd_acnow === TRUE) echo 'AC NOW'."\n";
  if ($cmd_chargenow === TRUE) echo 'CHARGE NOW'."\n";
  if ($cmd_cmon === TRUE) echo 'CM ON'."\n";
  else if ($cmd_cmoff === TRUE) echo 'CM OFF'."\n";
  if ($update_sucess === TRUE) echo 'OK';
  else echo 'NO DATA';
} else {
  $requesturi = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
  echo '<HTML>'."\n".'<HEAD>'."\n".'<LINK REL="manifest" HREF="zoephp.webmanifest">'."\n".'<LINK REL="stylesheet" HREF="stylesheet.css">'."\n".'<META NAME="viewport" CONTENT="width=device-width, initial-scale=1.0">'."\n".'<TITLE>'.$zoename.'</TITLE>'."\n".'</HEAD>'."\n".'<BODY>'."\n".'<DIV ID="container">'."\n".'<MAIN>'."\n";
  if ($mail_bl === 'Y') echo '<FORM ACTION="'.$requesturi.'" METHOD="post" AUTOCOMPLETE="off">'."\n";
  echo '<ARTICLE>'."\n".'<TABLE>'."\n".'<TR ALIGN="left"><TH>'.$zoename.'</TH><TD><SMALL><A HREF="'.$requesturi.'">'.$lng['Update'].'</A></SMALL></TD></TR>'."\n";
  if ($cmd_acnow === TRUE) echo '<TR><TD COLSPAN="2">'.$lng['Preconditioning requested.'].'</TD><TD>'."\n";
  if ($cmd_chargenow === TRUE) echo '<TR><TD COLSPAN="2">'.$lng['Instant charging requested.'].'</TD><TD>'."\n";
  if ($cmd_cmon === TRUE) echo '<TR><TD COLSPAN="2">'.$lng['Activation of the charging schedule requested.'].'</TD><TD>'."\n";
  else if ($cmd_cmoff === TRUE) echo '<TR><TD COLSPAN="2">'.$lng['Deactivation of the charging schedule requested.'].'</TD><TD>'."\n";
  if ($update_sucess === FALSE && $update_ok === TRUE) echo '<TR><TD COLSPAN="2">'.$lng['No new data'].'</TD><TD>'."\n";
    echo '<TR><TD>'.$lng['Mileage'].':</TD><TD>'.$session['Mileage'].' km</TD></TR>'."\n".'<TR><TD>'.$lng['Connected'].':</TD><TD>';
    if ($session['Cable status'] == 0){
      echo $lng['No'];
    } else {
      echo $lng['Yes'];
    }
    echo '</TD></TR>'."\n".'<TR><TD>'.$lng['Charging'].':</TD><TD>';
    if ($session['Charging status'] == 1){
	  if ($session['Charging time'] != ''){
        $s = date_create_from_format('d.m.YH:i', $session['Date status update'].$session['Time status update']);
        date_add($s, date_interval_create_from_date_string($session['Charging time'].' minutes'));
        $s = date_format($s, 'H:i');
      } else $s = $lng['Soon'];
      echo $lng['Yes'].'</TD></TR>'."\n".'<TR><TD>'.$lng['Ready'].':</TD><TD>'.$s;
	  if ($zoeph == 1) echo '</TD></TR>'."\n".'<TR><TD>'.$lng['Effect'].':</TD><TD>'.$session['Charging effect'].' kW';
    } else {
      echo $lng['No'];
    }
	if ($hide_cm !== 'Y') {
	  echo '</TD></TR>'."\n".'<TR><TD>'.$lng['Charging schedule'].':</TD><TD>';
	  if (substr($session['Chargemode'], 0, 6) === 'always' || $session['Chargemode'] === 'n/a') echo $lng['Inactive'];
	  else echo $lng['Active'];
    }
    echo '</TD></TR>'."\n".'<TR><TD>'.$lng['Battery level'].':</TD><TD>'.$session['Battery level'].' %</TD></TR>'."\n";
	if ($mail_bl === 'Y' || $cmon_bl === 'Y' || !empty($exec_bl)) echo '<TR><TD>'.$lng['Action at battery level'].':</TD><TD><INPUT TYPE="number" NAME="bl" VALUE="'.$session['Setting battery level'].'" MIN="1" MAX="99"><INPUT TYPE="submit" VALUE="%"></TD></TR>'."\n";
    if ($zoeph == 2) {
      echo '<TR><TD>'.$lng['Battery capacity'].':</TD><TD>'.$session['Battery temp-range'].' kWh</TD></TR>'."\n";
    }
    echo '<TR><TD>'.$lng['Range'].':</TD><TD>'.$session['Range'].' km</TD></TR>'."\n";
    if ($zoeph == 1) {
      echo '<TR><TD>'.$lng['Battery temperature'].':</TD><TD>'.$session['Battery temp-range'].' &deg;C</TD></TR>'."\n".'<TR><TD>'.$lng['Outside temperature'].':</TD><TD>'.$session['Outside temp-GPSlat'].' &deg;C</TD></TR>'."\n";
    } else {
	  if ($weather_api_key != '') echo '<TR><TD>'.$lng['Outside temperature'].':</TD><TD>'.$session['Outside temp'].' &deg;C ('.htmlentities($session['Weather']).')</TD></TR>'."\n";
	}
    echo '<TR><TD>'.$lng['Status update'].':</TD><TD>'.$session['Date status update'].' '.$session['Time status update'].'</TD></TR>'."\n";
    if ($zoeph == 2) {
      echo '<TR><TD>'.$lng['Car position'].':</TD><TD><A HREF="https://www.google.com/maps/place/'.$session['Outside temp-GPSlat'].','.$session['GPSlong'].'" TARGET="_blank">Google Maps</A></TD></TR>'."\n".'<TR><TD>'.$lng['Position update'].':</TD><TD>'.$session['GPS date'].' '.$session['GPS time'].'</TD></TR>'."\n";
    }
  echo '<TR><TD COLSPAN="2"><A HREF="'.$requesturi.'?acnow">'.$lng['Start preconditioning'].'</A></TD></TR>'."\n";
  if ($hide_cm !== 'Y') echo '<TR><TD COLSPAN="2">'.$lng['Charging schedule'].': <A HREF="'.$requesturi.'?cmon">'.$lng['on'].'</A> | <A HREF="'.$requesturi.'?cmoff">'.$lng['off'].'</A></TD></TR>'."\n".'<TR><TD COLSPAN="2"><A HREF="'.$requesturi.'?chargenow">'.$lng['Start charging'].'</A></TD></TR>'."\n";
  if ($zoeph == 1) echo '<TR><TD COLSPAN="2"><A HREF="history.php">'.$lng['Charging history'].'</A></TD></TR>'."\n";
  echo '</TABLE>'."\n".'</ARTICLE>'."\n";
  if ($mail_bl === 'Y') echo '</FORM>'."\n";
  echo '</MAIN>'."\n".'</DIV>'."\n".'</BODY>'."\n".'</HTML>';
}

//Cache data
if ($update_ok === TRUE || $cmd_cron == TRUE || (isset($_POST['bl']) && is_numeric($_POST['bl']))) {
  $session['MD5 hash'] = $md5;
  $session['Timestamp last data'] = $timestamp_now;
  $session = implode('|', $session);
  file_put_contents('session', $session);
}
?>
