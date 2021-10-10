<?php
$showResponse=true;
 echo '<HTML>'."\n".'<HEAD>'."\n".'<LINK REL="manifest" HREF="zoephp.webmanifest">'."\n".'<LINK REL="stylesheet" HREF="stylesheet.css">'."\n".'<META NAME="viewport" CONTENT="width=device-width, initial-scale=1.0">'."\n".'<TITLE>'.$zoename.'</TITLE>'."\n".'</HEAD>'."\n".'<BODY>'."\n";
session_cache_limiter('nocache');
require 'api-keys.php';
require 'config.php';
require "tests-LC.php";
require "security.php";
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
 * Session array:
 * 0: Date Gigya JWT Token request (md)
 * 1: Gigya JWT Token
 * 2: Renault account id
 * 3: MD5 hash of the last data retrieval
 * 4: Timestamp of the last data retrieval (YmdHi)
 * 5: Action done when reaching battery level (Y/N)
 * 6: Car is charging (Y/N)
 * 7: Mileage
 * 8: Date status update
 * 9: Time status update
 * 10: Charging status
 * 11: Cable status
 * 12: Battery level
 * 13: Battery temperature (Ph1) / battery capacity (Ph2)
 * 14: Range in km
 * 15: Charging time
 * 16: Charging effect
 * 17: Outside temperature (Ph1) / GPS-Latitude (Ph2)
 * 18: GPS-Longitude (Ph2)
 * 19: GPS date (Ph2, d.m.Y)
 * 20: GPS time (Ph2, H:i)
 * 21: Setting battery level for mail function
 * 22: Outside temperature (Ph2, openweathermap API)
 * 23: Weather condition (Ph2, openweathermap API)
 * 24: Chargemode
 */
//$session = file_get_contents('session');
//if ($session !== FALSE) $session = explode('|', $session);
//else $session = array('0000', '', '', '', '202001010000', 'N', 'N', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '80','','','');
require 'session-definition.php';

//Retrieve setting battery level for mail function
if (isset($_POST['bl']) && is_numeric($_POST['bl']) && $_POST['bl'] >= 1 && $_POST['bl'] <= 99) {
  if ($_POST['bl'] > $session[21]) $session[5] = 'N';
  $session[21] = $_POST['bl'];
}

//Checking cron time interval
if ($cmd_cron == TRUE) {
  $s = date_create_from_format('YmdHi', $session[4]);
  if ($session[6] == 'Y') date_add($s, date_interval_create_from_date_string($cron_acs.' minutes'));
  else date_add($s, date_interval_create_from_date_string($cron_ncs.' minutes'));
  $s = date_format($s, 'YmdHi');
  if ($timestamp_now < $s) exit('INTERVAL NOT REACHED');
}

//Max one API request per minute
$s = date_create_from_format('YmdHi', $session[4]);
date_add($s, date_interval_create_from_date_string('1 minutes'));
$s = date_format($s, 'YmdHi');
if ($timestamp_now < $s) $update_ok = FALSE;
else $update_ok = TRUE;

//Retrieve new Gigya token if the date has changed since last request
if (empty($session[1]) || $session[0] !== $date_today) {
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
  if ($response === FALSE) {
		die(curl_error($ch));
	} else {
		if (strpos($response,'"errorCode"') !== false )  {
			if (strpos($response,'"errorCode": 0') === false )  {
					echo "Error 001: <pre> " . $response . "</pre><br>";
					die(curl_error($ch));
			} else {
		 		if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
			}
		} else {
		 if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
		}
	}


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
  if ($response === FALSE) {
		die(curl_error($ch));
	} else {
		if (strpos($response,'"errorCode"') !== false )  {
			if (strpos($response,'"errorCode": 0') === false )  {
					echo "Error 002: <pre> " . $response . "</pre><br>";
					die(curl_error($ch));
			} else {
		 		if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
			}
		} else {
		 if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
		}
	}



  $responseData = json_decode($response, TRUE);
  $session[1] = $responseData['id_token'];
  $session[0] = $date_today;
}

//Request Renault account id if not cached
if (empty($session[2])) {
  //Request Kamereon account id
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session[1],
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/persons/'.$personId.'?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) {
		die(curl_error($ch));
	} else {
		if (strpos($response,'"errorCode"') !== false )  {
			if (strpos($response,'"errorCode": 0') === false )  {
					echo "Error 003: <pre> " . $response . "</pre><br>";
					die(curl_error($ch));
			} else {
		 		if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
			}
		} else {
		 if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
		}
	}

  $responseData = json_decode($response, TRUE);
  $session[2] = $responseData['accounts'][0]['accountId'];
}

//Evaluate parameter "acnow" for preconditioning
if ($cmd_acnow === TRUE) {
  $postData = array(
    'Content-type: application/vnd.api+json',
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session[1]
  );
  $jsonData = '{"data":{"type":"HvacStart","attributes":{"action":"start","targetTemperature":"21"}}}';
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/actions/hvac-start?country='.$country);
  curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
  $response = curl_exec($ch);
  if ($response === FALSE) {
		die(curl_error($ch));
	} else {
		if (strpos($response,'"errorCode"') !== false )  {
			if (strpos($response,'"errorCode": 0') === false )  {
					echo "Error 001: <pre> " . $response . "</pre><br>";
					die(curl_error($ch));
			} else {
		 		if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
			}
		} else {
		 if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
		}
	}

}

//Evaluate parameter "chargenow" for instant charging
if ($cmd_chargenow === TRUE) {
  $postData = array(
    'Content-type: application/vnd.api+json',
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session[1]
  );
  $jsonData = '{"data":{"type":"ChargingStart","attributes":{"action":"start"}}}';
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/actions/charging-start?country='.$country);
  curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
  $response = curl_exec($ch);
  if ($response === FALSE) {
		die(curl_error($ch));
	} else {
		if (strpos($response,'"errorCode"') !== false )  {
			if (strpos($response,'"errorCode": 0') === false )  {
					echo "Error 001: <pre> " . $response . "</pre><br>";
					die(curl_error($ch));
			} else {
		 		if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
			}
		} else {
		 if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
		}
	}

}

//Evaluate parameters "cmon" respectively "cmoff" for setting the chargemode
if ($cmd_cmon === TRUE || $cmd_cmoff === TRUE) {
  $postData = array(
    'Content-type: application/vnd.api+json',
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session[1]
  );
  if ($cmd_cmon === TRUE) $jsonData = '{"data":{"type":"ChargeMode","attributes":{"action":"schedule_mode"}}}';
  else $jsonData = '{"data":{"type":"ChargeMode","attributes":{"action":"always_charging"}}}';
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/actions/charge-mode?country='.$country);
  curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
  $response = curl_exec($ch);
  if ($response === FALSE) {
		die(curl_error($ch));
	} else {
		if (strpos($response,'"errorCode"') !== false )  {
			if (strpos($response,'"errorCode": 0') === false )  {
					echo "Error 001: <pre> " . $response . "</pre><br>";
					die(curl_error($ch));
			} else {
		 		if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
			}
		} else {
		 if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
		}
	}


}

//Request battery and charging status from Renault
if ($update_ok === TRUE) {
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session[1]
  );
	$queryUrl = 'https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v2/cars/'.$vin.'/battery-status?country='.$country;
  $ch = curl_init($queryUrl);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) {
		die(curl_error($ch));
	} else {
		if (strpos($response,'"errorCode"') !== false )  {
			if (strpos($response,'"errorCode": 0') === false )  {
					echo "Error 001: <pre> " . $response . "</pre><br>";
					die(curl_error($ch));
			} else {
		 		if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
			}
		} else {
		 if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
		}
	}


  $md5 = md5($response);
  $responseData = json_decode($response, TRUE);
  $s = date_create_from_format(DATE_ISO8601, $responseData['data']['attributes']['timestamp'], timezone_open('UTC'));
  $utc_timestamp = date_timestamp_get($s);
  if (empty($s)) $update_sucess = FALSE;
  else {
    $update_sucess = TRUE;
    $weather_api_dt = date_format($s, 'U');
    $s = date_timezone_set($s, timezone_open('Europe/Berlin'));
    $session[8] = date_format($s, 'd.m.Y');
    $session[9] = date_format($s, 'H:i');
    $session[10] = $responseData['data']['attributes']['chargingStatus'];
    $session[11] = $responseData['data']['attributes']['plugStatus'];
    $session[12] = $responseData['data']['attributes']['batteryLevel'];
    if (($zoeph == 1)) $session[13] = $responseData['data']['attributes']['batteryTemperature'];
    else $session[13] = $responseData['data']['attributes']['batteryAvailableEnergy'];
    $session[14] = $responseData['data']['attributes']['batteryAutonomy'];
    $session[15] = $responseData['data']['attributes']['chargingRemainingTime'];
    $s = $responseData['data']['attributes']['chargingInstantaneousPower'];
    if ($zoeph == 1) $session[16] = $s/1000;
    else $session[16] = $s;
  }
} else $update_sucess = FALSE;

//Request more data from Renault if changed data since last request are expected
if (isset($md5) && $md5 != $session[3] && $update_sucess === TRUE) {
  //Request mileage
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session[1]
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/cockpit?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) {
		die(curl_error($ch));
	} else {
		if (strpos($response,'"errorCode"') !== false )  {
			if (strpos($response,'"errorCode": 0') === false )  {
					echo "Error 001: <pre> " . $response . "</pre><br>";
					die(curl_error($ch));
			} else {
		 		if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
			}
		} else {
		 if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
		}
	}

  $responseData = json_decode($response, TRUE);
  $s = $responseData['data']['attributes']['totalMileage'];
  if (empty($s)) $update_sucess = FALSE;
  else $session[7] = $s;

  //Request chargemode
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session[1]
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/charge-mode?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) {
		die(curl_error($ch));
	} else {
		if (strpos($response,'"errorCode"') !== false )  {
			if (strpos($response,'"errorCode": 0') === false )  {
					echo "Error 001: <pre> " . $response . "</pre><br>";
					die(curl_error($ch));
			} else {
		 		if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
			}
		} else {
		 if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
		}
	}


  $responseData = json_decode($response, TRUE);
  $s = $responseData['data']['attributes']['chargeMode'];
  if (empty($s)) $session[24] = 'n/a';
  else $session[24] = $s;

  //Request outside temperature (only Ph1)
  if ($zoeph == 1) {
    $postData = array(
      'apikey: '.$kamereon_api,
      'x-gigya-id_token: '.$session[1]
    );
    $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/hvac-status?country='.$country);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
    $response = curl_exec($ch);
  if ($response === FALSE) {
		die(curl_error($ch));
	} else {
		if (strpos($response,'"errorCode"') !== false )  {
			if (strpos($response,'"errorCode": 0') === false )  {
					echo "Error 001: <pre> " . $response . "</pre><br>";
					die(curl_error($ch));
			} else {
		 		if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
			}
		} else {
		 if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
		}
	}


    $responseData = json_decode($response, TRUE);
    $s = $responseData['data']['attributes']['externalTemperature'];
    if (empty($s) && $s != '0.0') $update_sucess = FALSE;
    else $session[17] = $s;
  }

  //Request GPS position (only Ph2)
  if ($zoeph == 2) {
    $postData = array(
      'apikey: '.$kamereon_api,
      'x-gigya-id_token: '.$session[1]
    );
    $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/location?country='.$country);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
    $response = curl_exec($ch);
  if ($response === FALSE) {
		die(curl_error($ch));
	} else {
		if (strpos($response,'"errorCode"') !== false )  {
			if (strpos($response,'"errorCode": 0') === false )  {
					echo "Error 001: <pre> " . $response . "</pre><br>";
					die(curl_error($ch));
			} else {
		 		if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
			}
		} else {
		 if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
		}
	}

    $responseData = json_decode($response, TRUE);
    $s = date_create_from_format(DATE_ISO8601, $responseData['data']['attributes']['lastUpdateTime'], timezone_open('UTC'));
	if (empty($s)) $update_sucess = FALSE;
	else {
      $s = date_timezone_set($s, timezone_open('Europe/Berlin'));
	  $session[17] = $responseData['data']['attributes']['gpsLatitude'];
	  $session[18] = $responseData['data']['attributes']['gpsLongitude'];
      $session[19] = date_format($s, 'd.m.Y');
	  $session[20] = date_format($s, 'H:i');
	}
  }

  //Request weather data from openweathermap (only Ph2)
  if ($zoeph == 2 && $weather_api_key != '') {
	$ch = curl_init('https://api.openweathermap.org/data/2.5/onecall/timemachine?lat='.$session[17].'&lon='.$session[18].'&dt='.$weather_api_dt.'&units=metric&lang='.$weather_api_lng.'&appid='.$weather_api_key);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	$response = curl_exec($ch);
  if ($response === FALSE) {
		die(curl_error($ch));
	} else {
		if (strpos($response,'"errorCode"') !== false )  {
			if (strpos($response,'"errorCode": 0') === false )  {
					echo "Error 001: <pre> " . $response . "</pre><br>";
					die(curl_error($ch));
			} else {
		 		if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
			}
		} else {
		 if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
		}
	}


	$responseData = json_decode($response, TRUE);
	$session[22] = $responseData['current']['temp'];
	$session[23] = $responseData['current']['weather']['0']['description'];
  }

  //Send mail, execute command or activate schedule mode if configured
  if ($mail_bl === 'Y' || $cmon_bl === 'Y' || !empty($exec_bl)) {
    if ($session[12] >= $session[21] && $session[10] == 1 && $session[5] != 'Y') {
      if ($session[15] != '') $s = $session[15];
	  else $s = $lng['some'];
      $sendmessage = $lng['Specified battery level reached.']."\n".$lng['Battery level'].': '.$session[12].' %'."\n".$lng['Remaining charging time'].': '.$s.' '.$lng['minutes']."\n".$lng['Range'].': '.$session[14].' km'."\n".$lng['Status update'].': '.$session[8].' '.$session[9];
	  if ($mail_bl === 'Y') mail($username, $zoename, $sendmessage);
	  if ($cmon_bl === 'Y') {
	    $postData = array(
	      'Content-type: application/vnd.api+json',
	      'apikey: '.$kamereon_api,
	      'x-gigya-id_token: '.$session[1]
	    );
	    $jsonData = '{"data":{"type":"ChargeMode","attributes":{"action":"schedule_mode"}}}';
	    $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/actions/charge-mode?country='.$country);
	    curl_setopt($ch, CURLOPT_POST, TRUE);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	    curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
	    $response = curl_exec($ch);
  if ($response === FALSE) {
		die(curl_error($ch));
	} else {
		if (strpos($response,'"errorCode"') !== false )  {
			if (strpos($response,'"errorCode": 0') === false )  {
					echo "Error 001: <pre> " . $response . "</pre><br>";
					die(curl_error($ch));
			} else {
		 		if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
			}
		} else {
		 if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
		}
	}

	  }
      if (!empty($exec_bl)) shell_exec($exec_bl.' "'.$sendmessage.'"');
	  $session[5] = 'Y';
    } else if ($session[5] == 'Y' && $session[10] != 1) $session[5] = 'N';
  }
  if ($mail_csf === 'Y' || !empty($exec_csf)) {
    $sendmessage = $lng['Charging finished.']."\n".$lng['Battery level'].': '.$session[12].' %'."\n".$lng['Range'].': '.$session[14].' km'."\n".$lng['Status update'].': '.$session[8].' '.$session[9];
    if ($session[6] == 'Y' && $session[10] != 1) {
	  if ($mail_csf === 'Y') mail($username, $zoename, $sendmessage);
      if (!empty($exec_csf)) shell_exec($exec_bl.' "'.$sendmessage.'"');
	}
	if ($session[10] == 1) $session[6] = 'Y';
    else $session[6] = 'N';
  }



  //Send data to ABRP if configured
  if (!empty($abrp_token) && !empty($abrp_model)) {
    if ($session[10] == 1) $abrp_is_charging = 1;
    else $abrp_is_charging = 0;
    $jsonData = urlencode('{"car_model":"'.$abrp_model.'","utc":'.$utc_timestamp.',"soc":'.$session[12].',"odometer":'.$session[7].',"is_charging":'.$abrp_is_charging.'}');
    $ch = curl_init('https://api.iternio.com/1/tlm/send?api_key=fd99255b-91a0-45cd-9df5-d6baa8e50ef8&token='.$abrp_token.'&tlm='.$jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $response = curl_exec($ch);
  if ($response === FALSE) {
		die(curl_error($ch));
	} else {
		if (strpos($response,'"errorCode"') !== false )  {
			if (strpos($response,'"errorCode": 0') === false )  {
					echo "Error 001: <pre> " . $response . "</pre><br>";
					die(curl_error($ch));
			} else {
		 		if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
			}
		} else {
		 if  ($showResponse === true) echo "<pre>" . $response . "</pre><br>";
		}
	}


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
  $requesturi .= '?pass=miapasssegretissima&username=' . $username . '&password=' . $password . '&vin=' . $vin; // Added for security
  echo '<DIV ID="container">'."\n".'<MAIN>'."\n";
  if ($mail_bl === 'Y') echo '<FORM ACTION="'.$requesturi.'" METHOD="post" AUTOCOMPLETE="off">'."\n";
  echo '<ARTICLE>'."\n".'<TABLE>'."\n".'<TR ALIGN="left"><TH>'.$zoename.'</TH><TD><SMALL><A HREF="'.$requesturi.'">'.$lng['Update'].'</A></SMALL></TD></TR>'."\n";
  if ($cmd_acnow === TRUE) echo '<TR><TD COLSPAN="2">'.$lng['Preconditioning requested.'].'</TD><TD>'."\n";
  if ($cmd_chargenow === TRUE) echo '<TR><TD COLSPAN="2">'.$lng['Instant charging requested.'].'</TD><TD>'."\n";
  if ($cmd_cmon === TRUE) echo '<TR><TD COLSPAN="2">'.$lng['Activation of the charging schedule requested.'].'</TD><TD>'."\n";
  else if ($cmd_cmoff === TRUE) echo '<TR><TD COLSPAN="2">'.$lng['Deactivation of the charging schedule requested.'].'</TD><TD>'."\n";
  if ($update_sucess === FALSE && $update_ok === TRUE) echo '<TR><TD COLSPAN="2">'.$lng['No new data'].'</TD><TD>'."\n";
    echo '<TR><TD>'.$lng['Mileage'].':</TD><TD>'.$session[7].' km</TD></TR>'."\n".'<TR><TD>'.$lng['Connected'].':</TD><TD>';
    if ($session[11] == 0){
      echo $lng['No'];
    } else {
      echo $lng['Yes'];
    }
    echo '</TD></TR>'."\n".'<TR><TD>'.$lng['Charging'].':</TD><TD>';
    if ($session[10] == 1){
	  if ($session[15] != ''){
        $s = date_create_from_format('d.m.YH:i', $session[8].$session[9]);
        date_add($s, date_interval_create_from_date_string($session[15].' minutes'));
        $s = date_format($s, 'H:i');
      } else $s = $lng['Soon'];
      echo $lng['Yes'].'</TD></TR>'."\n".'<TR><TD>'.$lng['Ready'].':</TD><TD>'.$s;
	  if ($zoeph == 1) echo '</TD></TR>'."\n".'<TR><TD>'.$lng['Effect'].':</TD><TD>'.$session[16].' kW';
    } else {
      echo $lng['No'];
    }
	if ($hide_cm !== 'Y') {
	  echo '</TD></TR>'."\n".'<TR><TD>'.$lng['Charging schedule'].':</TD><TD>';
	  if (substr($session[24], 0, 6) === 'always' || $session[24] === 'n/a') echo $lng['Inactive'];
	  else echo $lng['Active'];
    }
    echo '</TD></TR>'."\n".'<TR><TD>'.$lng['Battery level'].':</TD><TD>'.$session[12].' %</TD></TR>'."\n";
	if ($mail_bl === 'Y' || $cmon_bl === 'Y' || !empty($exec_bl)) echo '<TR><TD>'.$lng['Action at battery level'].':</TD><TD><INPUT TYPE="number" NAME="bl" VALUE="'.$session[21].'" MIN="1" MAX="99"><INPUT TYPE="submit" VALUE="%"></TD></TR>'."\n";
    if ($zoeph == 2) {
      echo '<TR><TD>'.$lng['Battery capacity'].':</TD><TD>'.$session[13].' kWh</TD></TR>'."\n";
    }
    echo '<TR><TD>'.$lng['Range'].':</TD><TD>'.$session[14].' km</TD></TR>'."\n";
    if ($zoeph == 1) {
      echo '<TR><TD>'.$lng['Battery temperature'].':</TD><TD>'.$session[13].' &deg;C</TD></TR>'."\n".'<TR><TD>'.$lng['Outside temperature'].':</TD><TD>'.$session[17].' &deg;C</TD></TR>'."\n";
    } else {
	  if ($weather_api_key != '') echo '<TR><TD>'.$lng['Outside temperature'].':</TD><TD>'.$session[22].' &deg;C ('.htmlentities($session[23]).')</TD></TR>'."\n";
	}
    echo '<TR><TD>'.$lng['Status update'].':</TD><TD>'.$session[8].' '.$session[9].'</TD></TR>'."\n";
    if ($zoeph == 2) {
      echo '<TR><TD>'.$lng['Car position'].':</TD><TD><A HREF="https://www.google.com/maps/place/'.$session[17].','.$session[18].'" TARGET="_blank">Google Maps</A></TD></TR>'."\n".'<TR><TD>'.$lng['Position update'].':</TD><TD>'.$session[19].' '.$session[20].'</TD></TR>'."\n";
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
  $session[3] = $md5;
  $session[4] = $timestamp_now;
  $session = implode('|', $session);
  //file_put_contents('session', $session);
}
?>
