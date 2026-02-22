<?php
/**
 * Renault EV Dashboard — Debug / Raw API Response Viewer
 */
session_cache_limiter('nocache');
require __DIR__ . '/api-keys.php';
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$gigya_api = resolveGigyaKey($country, $gigya_keys, $gigya_keys['GB']);
echo "gigya-api-key: {$gigya_api}\n\n";

$sessionPath = __DIR__ . '/session';
$session     = sessionLoad($sessionPath);

$accountId = $session['account_id'];
$token     = $session['jwt_token'];

if (empty($accountId) || empty($token)) {
    die("Error: No cached session found. Load index.php first to authenticate.\n");
}

$endpoints = [
    'battery-status' => fn() => fetchBatteryStatus($accountId, $vin, $kamereon_api, $token, $country),
    'cockpit'        => fn() => fetchCockpit($accountId, $vin, $kamereon_api, $token, $country),
    'charge-mode'    => fn() => fetchChargeMode($accountId, $vin, $kamereon_api, $token, $country),
    'hvac-status'    => fn() => fetchHvacStatus($accountId, $vin, $kamereon_api, $token, $country),
    'location'       => fn() => fetchLocation($accountId, $vin, $kamereon_api, $token, $country),
];

foreach ($endpoints as $name => $fetcher) {
    echo "{$name}: ";
    try {
        $data = $fetcher();
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch (RuntimeException $e) {
        echo "ERROR — " . $e->getMessage();
    }
    echo "\n\n";
}
