<?php
if (!isset($_GET['pass'])) {
    die("Not authorized");
} else {
	if ($_GET['pass'] != "miapasssegretissima") {
		die("Not authorized");
	}
    

}

$username  = $_GET['username'];
$password = $_GET['password'];
$vin  = $_GET['vin'];

session_cache_limiter('nocache');
require 'api-keys.php';
require 'config.php';
header('Content-Type: text/plain; charset=utf-8');
if (empty(${$country})) $gigya_api = $GB;
else $gigya_api = ${$country};
echo 'gigya-api-key: '.$gigya_api."\n\n";



//Request cached login
$session = file_get_contents('session');
$session = explode('|', $session);

//Request battery and charging status from Renault
$postData = array(
  'apikey: '.$kamereon_api,
  'x-gigya-id_token: '.$session[1]
);
$ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v2/cars/'.$vin.'/battery-status?country='.$country);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
$response = curl_exec($ch);
echo 'battery-status: '.str_replace  ($vin, 'xxxxxx', $response). "\n\n";

$postData = array(
  'apikey: '.$kamereon_api,
  'x-gigya-id_token: '.$session[1], 
  'Content-type: application/vnd.api+json'
);

//Request mileage

$ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/cockpit?country='.$country);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
$response = curl_exec($ch);
if ($response === FALSE) die(curl_error($ch));
echo 'cockpit: '.  str_replace  ($vin, 'xxxxxx', $response). "\n\n";

//Request chargemode
$ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/charge-mode?country='.$country);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
$response = curl_exec($ch);
echo 'charge-mode:'.  str_replace  ($vin, 'xxxxxx', $response). "\n\n";

//Request outside temperature
$postData = array(
  'apikey: '.$kamereon_api,
  'x-gigya-id_token: '.$session[1]
);
$ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/hvac-status?country='.$country);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
$response = curl_exec($ch);
if ($response === FALSE) die(curl_error($ch));
echo 'hvac-status: '.str_replace  ($vin, 'xxxxxx', $response). "\n\n";

//Request GPS position
$ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/location?country='.$country);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
$response = curl_exec($ch);
if ($response === FALSE) die(curl_error($ch));
echo 'location: '.str_replace  ($vin, 'xxxxxx', $response). "\n\n";


//Request charging history
$ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v2/cars/'.$vin.'/charges?country='.$country.'&start='.date("Ymd", strtotime("-1 months")).'&end='.date("Ymd"));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
$response = curl_exec($ch);
if ($response === FALSE) die(curl_error($ch));
echo 'charges: '.str_replace  ($vin, 'xxxxxx', $response). "\n\n";
$responseData = json_decode($response, TRUE);
$data = array();
if (isset($responseData['data']['attributes']['charges'])) $data = $responseData['data']['attributes']['charges'];


//Request charging history 2  (FUNZIONA)
$ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/charge-history?country='.$country.'&type=day&start='.date("Ymd", strtotime("-1 months")).'&end='.date("Ymd"));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
$response = curl_exec($ch);
if ($response === FALSE) die(curl_error($ch));
echo 'charges history: '.str_replace  ($vin, 'xxxxxx', $response). "\n\n";
$responseData = json_decode($response, TRUE);
$data = array();
if (isset($responseData['data']['attributes']['chargeSummaries'])) $data = $responseData['data']['attributes']['chargeSummaries'];


//Request hvac  history 1
$ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/hvac-sessions?country='.$country.'&start='.date("Ymd", strtotime("-1 months")).'&end='.date("Ymd"));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
$response = curl_exec($ch);
if ($response === FALSE) die(curl_error($ch));
echo 'hvac sessions: '.str_replace  ($vin, 'xxxxxx', $response). "\n\n";
$responseData = json_decode($response, TRUE);
$data = array();
//if (isset($responseData['data']['attributes']['charges'])) $data = $responseData['data']['attributes']['charges'];


//Request hvac history 2
$ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/hvac-history?country='.$country.'&type=day&start='.date("Ymd", strtotime("-1 months")).'&end='.date("Ymd"));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
$response = curl_exec($ch);
if ($response === FALSE) die(curl_error($ch));
echo 'hvac 2: '.str_replace  ($vin, 'xxxxxx', $response). "\n\n";
$responseData = json_decode($response, TRUE);
$data = array();
//if (isset($responseData['data']['attributes']['charges'])) $data = $responseData['data']['attributes']['charges'];


//Request trip history
$ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v2/cars/'.$vin.'/trip-history?country='.$country.'&type=day&start='.date("Ymd", strtotime("-1 months")).'&end='.date("Ymd"));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
$response = curl_exec($ch);
if ($response === FALSE) die(curl_error($ch));
echo 'trips: '.str_replace  ($vin, 'xxxxxx', $response). "\n\n";
$responseData = json_decode($response, TRUE);
$dat1a = array();
//if (isset($responseData['data']['attributes']['charges'])) $data = $responseData['data']['attributes']['charges'];



curl_close($ch);
?>
