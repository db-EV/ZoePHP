<?php
/**
 * Helper functions for Renault EV Dashboard
 */

// ─── Session Management ─────────────────────────────────────────────

/**
 * Session keys with their default values.
 * Replaces the old numeric index array for readability and safety.
 */
function sessionDefaults(): array
{
    return [
        'token_date'       => '0000',
        'jwt_token'        => '',
        'account_id'       => '',
        'data_hash'        => '',
        'last_request'     => '202001010000',
        'bl_action_done'   => false,
        'is_charging'      => false,
        'mileage'          => '',
        'status_date'      => '',
        'status_time'      => '',
        'charging_status'  => 0,
        'plug_status'      => 0,
        'battery_level'    => 0,
        'range_km'         => 0,
        'charging_time'    => '',
        'charging_power'   => 0,
        'gps_lat'          => '',
        'gps_lon'          => '',
        'gps_date'         => '',
        'gps_time'         => '',
        'notify_bl'        => 80,
        'temperature'      => '',
        'weather'          => '',
        'charge_mode'      => '',
        'csrf_token'       => '',
    ];
}

/**
 * Load session from flat file, returning associative array.
 * Expects JSON format (created by sessionSave or migration.php).
 */
function sessionLoad(string $path): array
{
    $defaults = sessionDefaults();

    if (!file_exists($path) || ($raw = file_get_contents($path)) === false) {
        return $defaults;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $defaults;
    }

    $session = array_merge($defaults, $decoded);

    // Ensure no null values override string defaults — prevents
    // htmlspecialchars(null) TypeError in PHP 8.1+
    foreach ($defaults as $key => $default) {
        if ($session[$key] === null) {
            $session[$key] = $default;
        }
    }

    return $session;
}

/**
 * Save session to flat file with file locking.
 */
function sessionSave(string $path, array $session): bool
{
    $json = json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return filePutContentsLocked($path, $json);
}


// ─── HTTP / cURL ────────────────────────────────────────────────────

/**
 * Perform a GET request to the Kamereon API.
 *
 * @throws RuntimeException on cURL or API errors
 */
function kamereonGet(string $url, string $apiKey, string $token): array
{
    return curlRequest($url, [
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $apiKey,
            'x-gigya-id_token: ' . $token,
        ],
    ]);
}

/**
 * Perform a POST request to the Kamereon API with JSON body.
 *
 * @throws RuntimeException on cURL or API errors
 */
function kamereonPost(string $url, string $apiKey, string $token, array $payload): array
{
    return curlRequest($url, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-type: application/vnd.api+json',
            'apikey: ' . $apiKey,
            'x-gigya-id_token: ' . $token,
        ],
    ]);
}

/**
 * Perform a form-encoded POST request (used for Gigya auth).
 *
 * @throws RuntimeException on cURL errors
 */
function gigyaPost(string $url, array $formData): array
{
    return curlRequest($url, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => $formData,
    ]);
}

/**
 * Low-level cURL wrapper with consistent error handling.
 *
 * @throws RuntimeException on network or cURL errors
 */
function curlRequest(string $url, array $options = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, $options + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("cURL error: {$error}");
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        throw new RuntimeException("HTTP {$httpCode} from {$url}");
    }

    $decoded = json_decode($response, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('JSON decode error: ' . json_last_error_msg());
    }

    return $decoded ?? [];
}


// ─── Kamereon URL Builder ───────────────────────────────────────────

function kamereonBaseUrl(string $accountId): string
{
    return 'https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'
        . urlencode($accountId) . '/kamereon/kca/car-adapter';
}

function kamereonCarUrl(string $accountId, string $vin, string $endpoint, string $country, int $version = 1): string
{
    return kamereonBaseUrl($accountId)
        . "/v{$version}/cars/" . urlencode($vin)
        . "/{$endpoint}?country=" . urlencode($country);
}


// ─── Authentication ─────────────────────────────────────────────────

/**
 * Login via Gigya and retrieve JWT token + person ID.
 *
 * @return array{jwt_token: string, person_id: string}
 * @throws RuntimeException on auth failure
 */
function gigyaLogin(string $apiKey, string $username, string $password): array
{
    // Step 1: Login
    $loginResponse = gigyaPost('https://accounts.eu1.gigya.com/accounts.login', [
        'ApiKey'            => $apiKey,
        'loginId'           => $username,
        'password'          => $password,
        'include'           => 'data',
        'sessionExpiration' => 60,
    ]);

    if (empty($loginResponse['sessionInfo']['cookieValue'])) {
        $msg = $loginResponse['errorMessage'] ?? 'Unknown login error';
        throw new RuntimeException("Gigya login failed: {$msg}");
    }

    $personId   = $loginResponse['data']['personId'] ?? '';
    $oauthToken = $loginResponse['sessionInfo']['cookieValue'];

    // Step 2: Get JWT token
    $jwtResponse = gigyaPost('https://accounts.eu1.gigya.com/accounts.getJWT', [
        'login_token' => $oauthToken,
        'ApiKey'      => $apiKey,
        'fields'      => 'data.personId,data.gigyaDataCenter',
        'expiration'  => 87000,
    ]);

    if (empty($jwtResponse['id_token'])) {
        throw new RuntimeException('Failed to retrieve JWT token');
    }

    return [
        'jwt_token' => $jwtResponse['id_token'],
        'person_id' => $personId,
    ];
}

/**
 * Fetch the Kamereon account ID for a person.
 */
function fetchAccountId(string $personId, string $apiKey, string $token, string $country): string
{
    $url  = 'https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/persons/'
        . urlencode($personId) . '?country=' . urlencode($country);
    $data = kamereonGet($url, $apiKey, $token);

    return $data['accounts'][0]['accountId']
        ?? throw new RuntimeException('No account ID found');
}


// ─── Kamereon API Actions ───────────────────────────────────────────

function fetchBatteryStatus(string $accountId, string $vin, string $apiKey, string $token, string $country): array
{
    $url = kamereonCarUrl($accountId, $vin, 'battery-status', $country, 2);
    return kamereonGet($url, $apiKey, $token);
}

function fetchCockpit(string $accountId, string $vin, string $apiKey, string $token, string $country): array
{
    $url = kamereonCarUrl($accountId, $vin, 'cockpit', $country);
    return kamereonGet($url, $apiKey, $token);
}

function fetchChargeMode(string $accountId, string $vin, string $apiKey, string $token, string $country): array
{
    $url = kamereonCarUrl($accountId, $vin, 'charge-mode', $country);
    return kamereonGet($url, $apiKey, $token);
}

function fetchHvacStatus(string $accountId, string $vin, string $apiKey, string $token, string $country): array
{
    $url = kamereonCarUrl($accountId, $vin, 'hvac-status', $country);
    return kamereonGet($url, $apiKey, $token);
}

function fetchLocation(string $accountId, string $vin, string $apiKey, string $token, string $country): array
{
    $url = kamereonCarUrl($accountId, $vin, 'location', $country);
    return kamereonGet($url, $apiKey, $token);
}

function sendHvacStart(string $accountId, string $vin, string $apiKey, string $token, string $country, int $targetTemp = 21): array
{
    $url = kamereonCarUrl($accountId, $vin, 'actions/hvac-start', $country);
    return kamereonPost($url, $apiKey, $token, [
        'data' => [
            'type'       => 'HvacStart',
            'attributes' => ['action' => 'start', 'targetTemperature' => (string) $targetTemp],
        ],
    ]);
}

function sendChargingStart(string $accountId, string $vin, string $apiKey, string $token, string $country): array
{
    $url = kamereonCarUrl($accountId, $vin, 'actions/charging-start', $country);
    return kamereonPost($url, $apiKey, $token, [
        'data' => [
            'type'       => 'ChargingStart',
            'attributes' => ['action' => 'start'],
        ],
    ]);
}

function sendChargeMode(string $accountId, string $vin, string $apiKey, string $token, string $country, bool $scheduleMode): array
{
    $action = $scheduleMode ? 'schedule_mode' : 'always_charging';
    $url    = kamereonCarUrl($accountId, $vin, 'actions/charge-mode', $country);
    return kamereonPost($url, $apiKey, $token, [
        'data' => [
            'type'       => 'ChargeMode',
            'attributes' => ['action' => $action],
        ],
    ]);
}

function fetchChargingHistory(string $accountId, string $vin, string $apiKey, string $token, string $country): array
{
    $start = date('Ymd', strtotime('-1 month'));
    $end   = date('Ymd');
    $url   = kamereonCarUrl($accountId, $vin, 'charges', $country)
        . '&start=' . $start . '&end=' . $end;
    return kamereonGet($url, $apiKey, $token);
}


// ─── Weather ────────────────────────────────────────────────────────

function fetchWeather(string $lat, string $lon, string $dt, string $lang, string $apiKey): array
{
    $url = 'https://api.openweathermap.org/data/2.5/onecall/timemachine?'
        . http_build_query([
            'lat'   => $lat,
            'lon'   => $lon,
            'dt'    => $dt,
            'units' => 'metric',
            'lang'  => $lang,
            'appid' => $apiKey,
        ]);

    return curlRequest($url);
}


// ─── Notification Helpers ───────────────────────────────────────────

/**
 * Execute a command safely with the message as escaped argument.
 */
function execSafe(string $command, string $message): void
{
    if (empty($command)) {
        return;
    }
    $escaped = escapeshellarg($message);
    shell_exec($command . ' ' . $escaped);
}


// ─── CSV / Database ─────────────────────────────────────────────────

/**
 * Append a row to the CSV database with file locking.
 */
function csvAppend(string $path, array $fields, ?array $header = null): bool
{
    $writeHeader = !file_exists($path) && $header !== null;

    $fp = fopen($path, 'a');
    if ($fp === false) {
        return false;
    }

    if (flock($fp, LOCK_EX)) {
        if ($writeHeader) {
            fputcsv($fp, $header, ';');
        }
        fputcsv($fp, $fields, ';');
        flock($fp, LOCK_UN);
    }

    fclose($fp);
    return true;
}


// ─── File Helpers ───────────────────────────────────────────────────

function filePutContentsLocked(string $path, string $content): bool
{
    $fp = fopen($path, 'c');
    if ($fp === false) {
        return false;
    }

    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        fwrite($fp, $content);
        fflush($fp);
        flock($fp, LOCK_UN);
    }

    fclose($fp);
    return true;
}


// ─── Gigya API Key Resolution ───────────────────────────────────────

function resolveGigyaKey(string $country, array $keys, string $fallback): string
{
    return $keys[$country] ?? $fallback;
}


// ─── Timestamp / Date Helpers ───────────────────────────────────────

/**
 * Parse an ISO 8601 timestamp and convert to the local timezone.
 */
function parseApiTimestamp(string $isoString, string $timezone): ?DateTimeImmutable
{
    try {
        $dt = new DateTimeImmutable($isoString);
        return $dt->setTimezone(new DateTimeZone($timezone));
    } catch (\Exception $e) {
        return null;
    }
}

/**
 * Get current date and timestamp strings.
 */
function nowStrings(): array
{
    $now = new DateTimeImmutable('now');
    return [
        'date_md'      => $now->format('md'),
        'timestamp_hi' => $now->format('YmdHi'),
    ];
}
