<?php
/**
 * Renault EV Dashboard — Main Entry Point
 *
 * Handles authentication, data retrieval, commands, and output.
 */
session_cache_limiter('nocache');
require __DIR__ . '/api-keys.php';
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

// Validate country against known values before using it in a file path
if (!in_array($country, ['DE', 'AT', 'IT', 'SE', 'GB'], true)) {
    $country = 'GB';
}

// Load language file
$lngFile = __DIR__ . '/lng/' . $country . '.php';
if (!file_exists($lngFile)) {
    $lngFile = __DIR__ . '/lng/EN.php';
}
require $lngFile;

// Resolve Gigya API key
$gigya_api = resolveGigyaKey($country, $gigya_keys, $gigya_keys['GB']);

// ─── Parse Commands ─────────────────────────────────────────────────

$isCli = PHP_SAPI === 'cli';
$cliArg = $argv[1] ?? '';

$cmd = [
    'cron'      => isset($_GET['cron'])      || $cliArg === 'cron',
    'acnow'     => isset($_GET['acnow'])     || $cliArg === 'acnow',
    'chargenow' => isset($_GET['chargenow']) || $cliArg === 'chargenow',
    'cmon'      => isset($_GET['cmon'])       || $cliArg === 'cmon',
    'cmoff'     => isset($_GET['cmoff'])      || $cliArg === 'cmoff',
];

// cmon and cmoff are mutually exclusive
if ($cmd['cmon']) {
    $cmd['cmoff'] = false;
}

if ($cmd['cron']) {
    header('Content-Type: text/plain; charset=utf-8');
} else {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'none'; img-src 'self' data:; manifest-src 'self'");
}

// ─── Load Session ───────────────────────────────────────────────────

$sessionPath = __DIR__ . '/session';
$session     = sessionLoad($sessionPath);
$now         = nowStrings();
$dateToday   = $now['date_md'];
$timestampNow = $now['timestamp_hi'];

// Generate CSRF token if not present
if (empty($session['csrf_token'])) {
    $session['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $session['csrf_token'];

// Update battery level notification threshold from POST (with CSRF check)
$blValue = filter_var($_POST['bl'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
if ($blValue !== false) {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, $postedToken)) {
        // CSRF validation failed — ignore POST
    } else {
        if ($blValue > $session['notify_bl']) {
            $session['bl_action_done'] = false;
        }
        $session['notify_bl'] = $blValue;
    }
}

// ─── Cron Interval Check ────────────────────────────────────────────

if ($cmd['cron']) {
    $lastRequest = DateTimeImmutable::createFromFormat('YmdHi', $session['last_request']);
    if ($lastRequest) {
        $interval = ($session['charging_status'] == 1 || $session['is_charging'])
            ? $cron_acs : $cron_ncs;
        $nextAllowed = $lastRequest->modify("+{$interval} minutes");
        $nowDt = new DateTimeImmutable('now');
        if ($nowDt < $nextAllowed) {
            exit('INTERVAL NOT REACHED');
        }
    }
}

// ─── Rate Limiting (1 request per minute) ───────────────────────────

$updateOk = true;
$lastRequest = DateTimeImmutable::createFromFormat('YmdHi', $session['last_request']);
if ($lastRequest) {
    $nextAllowed = $lastRequest->modify('+1 minute');
    $nowDt = new DateTimeImmutable('now');
    if ($nowDt < $nextAllowed) {
        $updateOk = false;
    }
}

// ─── Authentication ─────────────────────────────────────────────────

$personId = '';

try {
    if (empty($session['jwt_token']) || $session['token_date'] !== $dateToday) {
        $updateOk = true;
        $auth = gigyaLogin($gigya_api, $username, $password);
        $session['jwt_token']  = $auth['jwt_token'];
        $session['token_date'] = $dateToday;
        $personId = $auth['person_id'];
    }

    // Fetch account ID if not cached
    if (empty($session['account_id'])) {
        if (empty($personId)) {
            // Re-authenticate to get person_id
            $auth = gigyaLogin($gigya_api, $username, $password);
            $session['jwt_token']  = $auth['jwt_token'];
            $session['token_date'] = $dateToday;
            $personId = $auth['person_id'];
        }
        $session['account_id'] = fetchAccountId(
            $personId, $kamereon_api, $session['jwt_token'], $country
        );
    }
} catch (RuntimeException $e) {
    if ($cmd['cron']) {
        exit('AUTH ERROR: ' . $e->getMessage());
    }
    // For web: continue with cached data, show generic error
    $authError = $lng['No new data'];
}

// Shortcuts
$accountId = $session['account_id'];
$token     = $session['jwt_token'];

// ─── Execute Commands ───────────────────────────────────────────────

$notices   = [];
$cmdSent   = false;
$cmdCooldownSec = 60;

try {
    if ($cmd['acnow'] && !empty($accountId)) {
        if (cmdAllowed($session, 'acnow', $cmdCooldownSec)) {
            sendHvacStart($accountId, $vin, $kamereon_api, $token, $country);
            $notices[] = $lng['Preconditioning requested.'];
            $session['last_cmd']['acnow'] = time();
            $cmdSent = true;
        } else {
            $notices[] = $lng['Command rate limited.'];
        }
    }

    if ($cmd['chargenow'] && !empty($accountId)) {
        if (cmdAllowed($session, 'chargenow', $cmdCooldownSec)) {
            sendChargingStart($accountId, $vin, $kamereon_api, $token, $country);
            $notices[] = $lng['Instant charging requested.'];
            $session['last_cmd']['chargenow'] = time();
            $cmdSent = true;
        } else {
            $notices[] = $lng['Command rate limited.'];
        }
    }

    if ($cmd['cmon'] && !empty($accountId)) {
        if (cmdAllowed($session, 'cmon', $cmdCooldownSec)) {
            sendChargeMode($accountId, $vin, $kamereon_api, $token, $country, true);
            $notices[] = $lng['Activation of the charging schedule requested.'];
            $session['last_cmd']['cmon'] = time();
            $cmdSent = true;
        } else {
            $notices[] = $lng['Command rate limited.'];
        }
    } elseif ($cmd['cmoff'] && !empty($accountId)) {
        if (cmdAllowed($session, 'cmoff', $cmdCooldownSec)) {
            sendChargeMode($accountId, $vin, $kamereon_api, $token, $country, false);
            $notices[] = $lng['Deactivation of the charging schedule requested.'];
            $session['last_cmd']['cmoff'] = time();
            $cmdSent = true;
        } else {
            $notices[] = $lng['Command rate limited.'];
        }
    }
} catch (RuntimeException $e) {
    $notices[] = 'Command error: ' . $e->getMessage();
}

// ─── Fetch Battery Status ───────────────────────────────────────────

$updateSuccess = false;
$md5 = $session['data_hash'];

if ($updateOk && !empty($accountId)) {
    try {
        $batteryData = fetchBatteryStatus($accountId, $vin, $kamereon_api, $token, $country);
        $md5 = md5(json_encode($batteryData));

        if (isset($batteryData['data']['attributes'])) {
            $updateSuccess = true;
            $attrs = $batteryData['data']['attributes'];

            $statusDt = parseApiTimestamp($attrs['timestamp'], $timezone);
            if ($statusDt) {
                $utcTimestamp = $statusDt->getTimestamp();
                $weatherApiDt = $utcTimestamp;
                $session['status_date'] = $statusDt->format('d.m.Y');
                $session['status_time'] = $statusDt->format('H:i');
            }

            $session['charging_status'] = $attrs['chargingStatus'] ?? 0;
            $session['plug_status']     = $attrs['plugStatus'] ?? 0;
            $session['battery_level']   = $attrs['batteryLevel'] ?? 0;
            $session['range_km']        = $attrs['batteryAutonomy'] ?? 0;
            $session['charging_time']   = $attrs['chargingRemainingTime'] ?? '';

            $power = $attrs['chargingInstantaneousPower'] ?? 0;
            $session['charging_power'] = ($zoeph == 1) ? $power / 1000 : $power;
        }
    } catch (RuntimeException $e) {
        // Battery status unavailable — continue with cached data
    }
}

// ─── Fetch Additional Data (only when data changed) ─────────────────

if ($md5 !== $session['data_hash'] && $updateSuccess) {
    try {
        // Mileage
        $cockpitData = fetchCockpit($accountId, $vin, $kamereon_api, $token, $country);
        $mileage = $cockpitData['data']['attributes']['totalMileage'] ?? null;
        if ($mileage !== null) {
            $session['mileage'] = $mileage;
        } else {
            $updateSuccess = false;
        }

        // Charge mode
        $chargeModeData = fetchChargeMode($accountId, $vin, $kamereon_api, $token, $country);
        $session['charge_mode'] = $chargeModeData['data']['attributes']['chargeMode'] ?? 'n/a';
    } catch (RuntimeException $e) {
        // Additional data fetch failed
    }
}

// ─── Fetch GPS and Weather (Ph2, on every update) ───────────────────

if ($updateOk && $zoeph == 2 && !empty($accountId)) {
    // GPS
    try {
        $locationData = fetchLocation($accountId, $vin, $kamereon_api, $token, $country);
        $locAttrs = $locationData['data']['attributes'] ?? [];
        if (!empty($locAttrs['lastUpdateTime'])) {
            $gpsDt = parseApiTimestamp($locAttrs['lastUpdateTime'], $timezone);
            if ($gpsDt) {
                $session['gps_lat']  = (string) ($locAttrs['gpsLatitude'] ?? '');
                $session['gps_lon']  = (string) ($locAttrs['gpsLongitude'] ?? '');
                $session['gps_date'] = $gpsDt->format('d.m.Y');
                $session['gps_time'] = $gpsDt->format('H:i');
            }
        }
    } catch (RuntimeException $e) {
        // GPS unavailable
    }

    // Weather (requires API key and GPS data)
    if ($weather_api_key !== '' && !empty($session['gps_lat'])) {
        try {
            $weatherData = fetchWeather(
                $session['gps_lat'], $session['gps_lon'],
                (string) ($weatherApiDt ?? time()),
                $weather_api_lng ?? strtolower($country),
                $weather_api_key
            );
            $session['temperature'] = $weatherData['current']['temp'] ?? '';
            $session['weather']     = $weatherData['current']['weather'][0]['description'] ?? '';
        } catch (RuntimeException $e) {
            // Weather unavailable
        }
    }
}

// ─── Notifications & Data Export (only when data changed) ───────────

if ($md5 !== $session['data_hash'] && $updateSuccess) {

    // Battery level reached notification
    if ($mail_bl || $cmon_bl || !empty($exec_bl)) {
        $isCharging  = ($session['charging_status'] == 1);
        $levelReached = ($session['battery_level'] >= $session['notify_bl']);

        if ($levelReached && $isCharging && !$session['bl_action_done']) {
            $remaining = $session['charging_time'] ?: $lng['some'];
            $message = implode("\n", [
                $lng['Specified battery level reached.'],
                $lng['Battery level'] . ': ' . $session['battery_level'] . ' %',
                $lng['Remaining charging time'] . ': ' . $remaining . ' ' . $lng['minutes'],
                $lng['Range'] . ': ' . $session['range_km'] . ' km',
                $lng['Status update'] . ': ' . $session['status_date'] . ' ' . $session['status_time'],
            ]);

            if ($mail_bl)        mail($username, $zoename, $message);
            if ($cmon_bl)        sendChargeMode($accountId, $vin, $kamereon_api, $token, $country, true);
            if (!empty($exec_bl)) execSafe($exec_bl, $message);

            $session['bl_action_done'] = true;
        } elseif ($session['bl_action_done'] && !$isCharging) {
            $session['bl_action_done'] = false;
        }
    }

    // Charging finished notification
    if ($mail_csf || !empty($exec_csf)) {
        $isCharging = ($session['charging_status'] == 1);
        $message = implode("\n", [
            $lng['Charging finished.'],
            $lng['Battery level'] . ': ' . $session['battery_level'] . ' %',
            $lng['Range'] . ': ' . $session['range_km'] . ' km',
            $lng['Status update'] . ': ' . $session['status_date'] . ' ' . $session['status_time'],
        ]);

        if ($session['is_charging'] && !$isCharging) {
            if ($mail_csf)         mail($username, $zoename, $message);
            if (!empty($exec_csf)) execSafe($exec_csf, $message);
        }

        $session['is_charging'] = $isCharging;
    }

    // ─── CSV Database ───────────────────────────────────────────────

    if ($updateSuccess && $save_in_db) {
        if ($zoeph == 1) {
            $header = ['Date','Time','Mileage','Outside temperature','Battery temperature','Battery level','Range','Cable status','Charging status','Charging speed','Remaining charging time','Charging schedule'];
            $fields = [$session['status_date'], $session['status_time'], $session['mileage'], '', '', $session['battery_level'], $session['range_km'], $session['plug_status'], $session['charging_status'], $session['charging_power'], $session['charging_time'], $session['charge_mode']];
        } elseif ($zoeph == 2) {
            $header = ['Date','Time','Mileage','Battery level','Battery capacity','Range','Cable status','Charging status','Charging speed','Remaining charging time','GPS Latitude','GPS Longitude','GPS date','GPS time','Outside temperature','Weather condition','Charging schedule'];
            $fields = [$session['status_date'], $session['status_time'], $session['mileage'], $session['battery_level'], '', $session['range_km'], $session['plug_status'], $session['charging_status'], $session['charging_power'], $session['charging_time'], $session['gps_lat'], $session['gps_lon'], $session['gps_date'], $session['gps_time'], $session['temperature'], $session['weather'], $session['charge_mode']];
        } else {
            $header = ['Date','Time','Mileage','Battery level','Battery capacity','Range','Cable status','Charging status','Charging speed','Remaining charging time','GPS Latitude','GPS Longitude','GPS date','GPS time','Outside temperature','Weather condition','Charging schedule'];
            $fields = [$session['status_date'], $session['status_time'], $session['mileage'], $session['battery_level'], '', $session['range_km'], $session['plug_status'], $session['charging_status'], $session['charging_power'], $session['charging_time'], '', '', '', '', '', '', $session['charge_mode']];
        }

        csvAppend(__DIR__ . '/database.csv', $fields, $header);
    }

    // ─── ABRP Integration ───────────────────────────────────────────

    if (!empty($abrp_token) && !empty($abrp_model) && isset($utcTimestamp)) {
        try {
            $abrpData = json_encode([
                'car_model'  => $abrp_model,
                'utc'        => $utcTimestamp,
                'soc'        => $session['battery_level'],
                'odometer'   => $session['mileage'],
                'is_charging' => ($session['charging_status'] == 1) ? 1 : 0,
            ]);
            $abrpUrl = 'https://api.iternio.com/1/tlm/send?'
                . http_build_query([
                    'api_key' => 'fd99255b-91a0-45cd-9df5-d6baa8e50ef8',
                    'token'   => $abrp_token,
                    'tlm'     => $abrpData,
                ]);
            curlRequest($abrpUrl);
        } catch (RuntimeException $e) {
            // ABRP send failed — non-critical
        }
    }
}

// ─── Output ─────────────────────────────────────────────────────────

if ($cmd['cron']) {
    // CLI/cron output
    if ($cmd['acnow'])     echo "AC NOW\n";
    if ($cmd['chargenow']) echo "CHARGE NOW\n";
    if ($cmd['cmon'])      echo "CM ON\n";
    elseif ($cmd['cmoff']) echo "CM OFF\n";
    echo $updateSuccess ? 'OK' : 'NO DATA';
} else {
    // Web output
    if (!$updateSuccess && $updateOk) {
        $notices[] = $lng['No new data'];
    }
    if (isset($authError)) {
        $notices[] = $authError;
    }

    // Calculate "ready" time
    $readyTime = $lng['Soon'];
    if ($session['charging_status'] == 1 && $session['charging_time'] !== '') {
        $readyDt = DateTimeImmutable::createFromFormat(
            'd.m.YH:i',
            $session['status_date'] . $session['status_time']
        );
        if ($readyDt) {
            $readyTime = $readyDt->modify('+' . (int) $session['charging_time'] . ' minutes')->format('H:i');
        }
    }

    $requestUri = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';

    require __DIR__ . '/templates/dashboard.php';
}

// ─── Save Session ───────────────────────────────────────────────────

if ($updateOk || $cmd['cron'] || $cmdSent || $blValue !== false) {
    $session['data_hash']    = $md5;
    $session['last_request'] = $timestampNow;
    sessionSave($sessionPath, $session);
}
