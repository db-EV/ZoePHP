<?php
/**
 * Renault EV Dashboard — Charging History
 */
session_cache_limiter('nocache');
require __DIR__ . '/api-keys.php';
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

$lngFile = __DIR__ . '/lng/' . $country . '.php';
if (!file_exists($lngFile)) {
    $lngFile = __DIR__ . '/lng/EN.php';
}
require $lngFile;

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'none'; img-src 'self' data:; manifest-src 'self'");

$gigya_api   = resolveGigyaKey($country, $gigya_keys, $gigya_keys['GB']);
$sessionPath = __DIR__ . '/session';
$session     = sessionLoad($sessionPath);
$now         = nowStrings();
$dateToday   = $now['date_md'];
$tokenRefreshed = false;

// ─── Refresh Token if Needed ────────────────────────────────────────

try {
    if ($session['token_date'] !== $dateToday) {
        $auth = gigyaLogin($gigya_api, $username, $password);
        $session['jwt_token']  = $auth['jwt_token'];
        $session['token_date'] = $dateToday;
        $tokenRefreshed = true;
    }

    // ─── Fetch Charging History ─────────────────────────────────────

    $historyData = fetchChargingHistory(
        $session['account_id'], $vin, $kamereon_api,
        $session['jwt_token'], $country
    );

    $rawCharges = $historyData['data']['attributes']['charges'] ?? [];

    // Sort by start date descending
    usort($rawCharges, fn($a, $b) => ($b['chargeStartDate'] ?? '') <=> ($a['chargeStartDate'] ?? ''));

    // Transform into template-friendly format
    $tz = new DateTimeZone($timezone);
    $charges = [];

    foreach ($rawCharges as $entry) {
        if (empty($entry['chargeStartDate']) || empty($entry['chargeEndDate']) || empty($entry['chargeEnergyRecovered'])) {
            continue;
        }

        try {
            $startDt = new DateTimeImmutable($entry['chargeStartDate']);
            $endDt   = new DateTimeImmutable($entry['chargeEndDate']);
        } catch (\Exception $e) {
            continue;
        }

        $startDt = $startDt->setTimezone($tz);
        $endDt   = $endDt->setTimezone($tz);

        $diffMinutes = (int) (($endDt->getTimestamp() - $startDt->getTimestamp()) / 60);
        $energy      = round($entry['chargeEnergyRecovered'], 2);
        $avgPower    = $diffMinutes > 0 ? round($energy * 60 / $diffMinutes, 2) : 0;

        $charges[] = [
            'start_date'   => $startDt->format('d.m.Y'),
            'start_time'   => $startDt->format('H:i'),
            'end_date'     => $endDt->format('d.m.Y'),
            'end_time'     => $endDt->format('H:i'),
            'energy'       => $energy,
            'duration_min' => $diffMinutes,
            'avg_power'    => $avgPower,
            'end_status'   => $entry['chargeEndStatus'] ?? '',
        ];
    }
} catch (RuntimeException $e) {
    $charges = [];
}

// ─── Render ─────────────────────────────────────────────────────────

require __DIR__ . '/templates/history.php';

// ─── Save Token if Refreshed ────────────────────────────────────────

if ($tokenRefreshed) {
    sessionSave($sessionPath, $session);
}
