<?php
require 'security.php';


echo '<HTML>'."\n".'<HEAD>'."\n".'<LINK REL="stylesheet" HREF="stylesheet.css">'."\n".'<META NAME="viewport" CONTENT="width=device-width, initial-scale=1.0">'."\n".'<TITLE>Charges history LCS</TITLE>'."\n".'</HEAD>'."\n".'<BODY>'."\n";

if (isset($_GET['showResponse'])) {
	$showResponse = $_GET['showResponse'];
} else {
	$showResponse = null;
}



///////////////////////////
echo "<center><b><big><big><big>Renault unofficial dashboard</big></big></big></b></center><br><br>";
echo "Syntax:<br>";
echo "https://jumpjack.altervista.org/myrenault-debug/php/history.php?pass=miapasssegretissima&username=MYRENAULT_EMAIL&password=MYRENAULT_PASSWORD&&vin=MYVIN<br><br>";
echo "You can add these parameters to the url:<br>";
echo "<b>backmonths</b>: negative number - how many months back to start data from. (Works only with '-1'?)<br>";
echo "<b>groupingType</b>: day or month<br>";
echo "<b>dateRange</b> (overrides 'backmonths'): For day grouping use 8 figures  per date(YYYYMMDD-YYYYMMDD); for month grouping use 6 figures per date (YYYYMM-YYYYMM)=20210901-20210929<br><br><br>";


if (!isset($_GET['backmonths'])) {
    $backmonths = '-1';
	echo "Back months defaults to '" . $backmonths . "'<br>\n";;
} else {
	$backmonths = $_GET['backmonths'];
	echo "Back months set to '" . $backmonths . "' by user<br>\n";;
}



if (!isset($_GET['groupingType'])) {
    $groupingType = 'day';
	echo "groupingType defaults to '" . $groupingType . "'<br>\n";;
	echo "Listing all days; you can specify to group by month by adding '&groupingType=month' in url.";
} else {
	$groupingType = $_GET['groupingType'];
	echo "groupingType  set to '" . $groupingType . "' by user<br>\n";;
}



if (!isset($_GET['dateRange'])) {
	echo "dateRange not set.<br>\n";;
} else {
    $dateRange = $_GET['dateRange'];
	$bothDates = explode("-", $dateRange);
	$startDate =  $bothDates[0];
	$endDate =  $bothDates[1];
	$rangeUrl = "&start=" . $startDate . "&end=" . $endDate;
	echo "dateRange  set to '" . $dateRange . "' by user<br>";
	echo "Result: " . $rangeUrl. '<br><br>';
}



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


//Retrieve new Gigya token if the date has changed since last request
  //Login Gigya
  $update_authorized = TRUE;
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
		 		if  ($showResponse === "true") echo "<pre>" . json_encode(json_decode($response), JSON_PRETTY_PRINT) . "</pre><br>";
			}
		} else {
		 if  ($showResponse === "true") echo "<pre>" .json_encode(json_decode($response), JSON_PRETTY_PRINT) . "</pre><br>";
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
		 		if  ($showResponse === "true") echo "<pre>" . json_encode(json_decode($response), JSON_PRETTY_PRINT) . "</pre><br>";
			}
		} else {
		 if  ($showResponse === "true") echo "<pre>" . json_encode(json_decode($response), JSON_PRETTY_PRINT) . "</pre><br>";
		}
	}



  $responseData = json_decode($response, TRUE);
  $idtoken = $responseData['id_token'];


//Request Renault account id if not cached
  //Request Kamereon account id
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$idtoken,
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
		 		if  ($showResponse === "true") echo "<pre>" . json_encode(json_decode($response), JSON_PRETTY_PRINT) . "</pre><br>";
			}
		} else {
		 if  ($showResponse === "true") echo "<pre>" . json_encode(json_decode($response), JSON_PRETTY_PRINT) . "</pre><br>";
		}
	}

  $responseData = json_decode($response, TRUE);
  $accountId = $responseData['accounts'][1]['accountId'];





if ($groupingType == "day") {
	$finalUrl = 'https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/charge-history?country='.$country.'&type=' . $groupingType . '&start='.date("Ymd", strtotime($backmonths . " months")).'&end='.date("Ymd");
	echo "<br>d<br>" . $finalUrl . "<br>";
//	$ch = curl_init($finalUrl);
}


if ($groupingType == "month") {
	$finalUrl = 'https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/charge-history?country='.$country.'&type=' . $groupingType . '&start='.date("Ym", strtotime($backmonths . " months")).'&end='.date("Ym");
	echo "<br>m<br>" . $finalUrl . "<br>";
//	$ch = curl_init($finalUrl);
}

if (isset($_GET['dateRange'])) {
	$finalUrl = 'https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/charge-history?country=' . $country . '&type=' . $groupingType  .	$rangeUrl;
	echo "<br>r<br>" . $finalUrl . "<br>";
//	$ch = curl_init($finalUrl);
//	echo "Request url:<br>\n";
//	echo  str_replace ( $vin ,'xxx',  str_replace  ( $accountId,'xxx',    $finalUrl  )) . "<br>";
}



//Request charging history
$postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$idtoken
);
$ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/charges?country='.$country.'&start='.date("Ymd", strtotime("-1 months")).'&end='.date("Ymd"));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
$response = curl_exec($ch);
  if ($response === FALSE) {
		die(curl_error($ch));
	} else {
		if (strpos($response,'"errorCode"') !== false )  {
			if (strpos($response,'"errorCode": 0') === false )  {
				echo "Error 006: <pre> " . $response . "</pre><br>";
				$postData = array(
				    'apikey: '.$kamereon_api,
				    'x-gigya-id_token: '.$idtoken
				);
				$ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v1/cars/'.$vin2.'/charges?country='.$country.'&start='.date("Ymd", strtotime("-1 months")).'&end='.date("Ymd"));
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
				$response = curl_exec($ch);
				  if ($response === FALSE) {
						die(curl_error($ch));
					} else {
						if (strpos($response,'"errorCode"') !== false )  {
							if (strpos($response,'"errorCode": 0') === false )  {
									echo "Error 007: <pre> " . $response . "</pre><br>";
									die(curl_error($ch));
							} else {
						 		if  ($showResponse === "true") echo "008<pre>" . json_encode(json_decode($response), JSON_PRETTY_PRINT) . "</pre><br>";
							}
						} else {
						 if  ($showResponse === "true") echo "009<pre>" . json_encode(json_decode($response), JSON_PRETTY_PRINT) . "</pre><br>";
						}
					}
			} else {
		 		if  ($showResponse === "true") echo "006<pre>" . json_encode(json_decode($response), JSON_PRETTY_PRINT) . "</pre><br>";
			}
		} else {
		 if  ($showResponse === "true") echo "006b<pre>" . json_encode(json_decode($response), JSON_PRETTY_PRINT) . "</pre><br>";
		}
	}
$responseData = json_decode($response, TRUE);
$data = array();
if (isset($responseData['data']['attributes']['charges'])) $data = $responseData['data']['attributes']['charges'];

$response = str_replace($vin, "xxxxxxxxxxxxxx", $response); // obfuscate personal data

$responseData = json_decode($response, TRUE);
$data = array();

	echo "<br>\nRaw response LC:<br><pre>";
	echo json_encode(json_decode($response), JSON_PRETTY_PRINT);



//Output
echo '<DIV ID="container">'."\n".'<MAIN>'."\n".'<ARTICLE>'."\n".'<TABLE border="0">'."\n".'<TR ALIGN="left"><TH>Charges history</TH></TR>'."\n".'<TR><TD COLSPAN="2"><HR></TD></TR>';
echo '<tr><td>'.$lng[140].'</td><td>'.$lng[141].'</td><td>'.$lng[142].'</td><td>'.$lng[44].'</td></tr>';

for ($i = 0; $i < count($data); $i++) {
  if (!empty($data[$i]['day']) ) {
	$sd = $data[$i]['day'];
    echo '<TR><TD>'.$sd.'</TD><TD>'.$data[$i]['totalChargesNumber'].'</TD><TD>'.$data[$i]['totalChargesEnergyRecovered'].'</TD><TD>'.$data[$i]['totalChargesDuration'].'</TD></TR>';
  }
	if (!empty($data[$i]['month']) ) {
	$sd = $data[$i]['month'];
    echo '<TR><TD>'.$sd.'</TD><TD>'.$data[$i]['totalChargesNumber'].'</TD><TD>'.$data[$i]['totalChargesEnergyRecovered'].'</TD><TD>'.$data[$i]['totalChargesDuration'].'</TD></TR>';
  }
}
echo '<TR><TD COLSPAN="2"><A HREF="./">'.$lng[48].'</A></TD></TR>'."\n".'</TABLE>'."\n".'</ARTICLE>'."\n";
echo '</MAIN>'."\n".'</DIV>'."\n".'</BODY>'."\n".'</HTML>';

echo "<br><br><br>Raw response for debugging:<br><br><pre>";
echo json_encode(json_decode($response), JSON_PRETTY_PRINT);;
echo "</pre><br><br>----------------------------------<br><br>";

$dayTemplate = '{
  "data": {
    "type": "Car",
    "id": "xxxxxx",
    "attributes": {
      "chargeSummaries": [
        {
          "day": "20210901",
          "totalChargesDuration": 223
        },
        {
          "day": "20210902",
          "totalChargesNumber": 1,
          "totalChargesEnergyRecovered": 2.8,
          "totalChargesDuration": 101
        },
        {
          "day": "20210903",
          "totalChargesNumber": 1,
          "totalChargesEnergyRecovered": 3.15,
          "totalChargesDuration": 109
        }
      ]
    }
  }
}';


$monthTemplate = '{"data":{"type":"Car","id":"xxxxxx","attributes":{"chargeSummaries":[{"month":"202101","totalChargesDuration":66}]}}}';;
$monthTemplate_Pretty = json_encode(json_decode($monthTemplate), JSON_PRETTY_PRINT);


echo "<br><br><b>Day-grouping response template:</b><br>";
echo "<pre>" . $dayTemplate . "</pre>";


echo "<br><b>Month-grouping response template:</b><br>";
echo "<pre>" . $monthTemplate_Pretty . "</pre>";
echo "<br><br><br><br>";
echo "Source: <a href='https://github.com/jumpjack/RenaultPHP_LC/tree/main/src'>Github</a><br><br><br>";

curl_close($ch);

?>
