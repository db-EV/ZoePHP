<?php
/**
 * Renault EV Dashboard — One-Time Migration Script
 *
 * Run this script ONCE after uploading the new files to migrate:
 * - config.php    ('Y'/'N' strings → booleans, adds timezone)
 * - session file  (pipe-separated → JSON)
 *
 * Usage:
 *   Browser: http://your-server/migration.php
 *   CLI:     php migration.php
 *
 * After successful migration, you can delete this file.
 */
header('Content-Type: text/plain; charset=utf-8');

$dir = __DIR__;
$errors   = [];
$migrated = [];

echo "=== Renault EV Dashboard Migration ===\n\n";

// ─── 1. Migrate config.php ──────────────────────────────────────────

echo "[1/2] config.php ... ";

require $dir . '/config.php';

// Detect old format: 'Y'/'N' strings or missing $timezone
$needsMigration = false;
if (is_string($save_in_db ?? null) && in_array($save_in_db, ['Y', 'N'], true)) $needsMigration = true;
if (is_string($mail_bl ?? null) && in_array($mail_bl, ['Y', 'N'], true)) $needsMigration = true;
if (!isset($timezone)) $needsMigration = true;

if (!$needsMigration) {
    echo "already in new format, skipping.\n";
} else {
    // Convert values
    $cfg_save_in_db = is_string($save_in_db ?? null) ? ($save_in_db === 'Y') : (bool) ($save_in_db ?? false);
    $cfg_mail_bl    = is_string($mail_bl ?? null) ? ($mail_bl === 'Y') : (bool) ($mail_bl ?? false);
    $cfg_cmon_bl    = is_string($cmon_bl ?? null) ? ($cmon_bl === 'Y') : (bool) ($cmon_bl ?? false);
    $cfg_mail_csf   = is_string($mail_csf ?? null) ? ($mail_csf === 'Y') : (bool) ($mail_csf ?? false);
    $cfg_hide_cm    = is_string($hide_cm ?? null) ? ($hide_cm === 'Y') : (bool) ($hide_cm ?? false);

    $cfg_country = $country ?? 'GB';
    $tzMap = [
        'DE' => 'Europe/Berlin', 'AT' => 'Europe/Vienna',
        'IT' => 'Europe/Rome',   'SE' => 'Europe/Stockholm',
        'GB' => 'Europe/London',
    ];
    $cfg_timezone = $timezone ?? ($tzMap[$cfg_country] ?? 'Europe/London');

    $bool = fn($v) => $v ? 'true' : 'false';

    $content = <<<PHP
<?php
/**
 * Configuration for Renault EV Dashboard
 */

// Display name (shown as heading)
\$zoename = {$_q = fn($v) => var_export($v, true); $_q($zoename ?? 'Renault EV')};

// Renault model: 1 = Zoe Ph1, 2 = Zoe Ph2 / MeganE
\$zoeph = {$_q($zoeph ?? 1)};

// My Renault login credentials
\$username = {$_q($username ?? '')};
\$password = {$_q($password ?? '')};

// Vehicle Identification Number
\$vin = {$_q($vin ?? '')};

// Registration country: DE, AT, IT, SE, GB
\$country = {$_q($cfg_country)};

// Timezone
\$timezones = [
    'DE' => 'Europe/Berlin',
    'AT' => 'Europe/Vienna',
    'IT' => 'Europe/Rome',
    'SE' => 'Europe/Stockholm',
    'GB' => 'Europe/London',
];
\$timezone = \$timezones[\$country] ?? 'Europe/London';

// Save data in database.csv
\$save_in_db = {$bool($cfg_save_in_db)};

// Notifications when battery level is reached
\$mail_bl = {$bool($cfg_mail_bl)};
\$exec_bl = {$_q($exec_bl ?? '')};
\$cmon_bl = {$bool($cfg_cmon_bl)};

// Notifications when charging is finished
\$mail_csf = {$bool($cfg_mail_csf)};
\$exec_csf = {$_q($exec_csf ?? '')};

// Hide charging schedule info and commands
\$hide_cm = {$bool($cfg_hide_cm)};

// Cron settings (interval in minutes)
\$cron_ncs = {$_q($cron_ncs ?? 60)};
\$cron_acs = {$_q($cron_acs ?? 15)};

// Map provider for Ph2: 'google' or 'osm'
\$map_provider = {$_q($map_provider ?? 'google')};

// OpenWeatherMap API key (Ph2 only)
\$weather_api_key = {$_q($weather_api_key ?? '')};

// ABRP integration
\$abrp_token = {$_q($abrp_token ?? '')};
\$abrp_model = {$_q($abrp_model ?? '')};

PHP;

    if (copy($dir . '/config.php', $dir . '/config.php.bak')) {
        if (file_put_contents($dir . '/config.php', $content)) {
            echo "migrated (backup: config.php.bak)\n";
            $migrated[] = 'config.php';
        } else {
            echo "ERROR: Could not write new file.\n";
            $errors[] = 'config.php';
        }
    } else {
        echo "ERROR: Could not create backup.\n";
        $errors[] = 'config.php';
    }
}

// ─── 2. Migrate session file ────────────────────────────────────────

echo "[2/2] session ... ";

$sessionPath = $dir . '/session';

if (!file_exists($sessionPath)) {
    echo "no session file found, skipping (will be created on first run).\n";
} else {
    $raw = file_get_contents($sessionPath);
    $decoded = json_decode($raw, true);

    if (is_array($decoded)) {
        echo "already in JSON format, skipping.\n";
    } else {
        // Old pipe-separated format
        $parts = explode('|', $raw);
        $mapping = [
            0  => 'token_date',      1  => 'jwt_token',
            2  => 'account_id',      3  => 'data_hash',
            4  => 'last_request',    5  => 'bl_action_done',
            6  => 'is_charging',     7  => 'mileage',
            8  => 'status_date',     9  => 'status_time',
            10 => 'charging_status', 11 => 'plug_status',
            12 => 'battery_level',
            // 13 was unused
            14 => 'range_km',        15 => 'charging_time',
            16 => 'charging_power',  17 => 'gps_lat',
            18 => 'gps_lon',         19 => 'gps_date',
            20 => 'gps_time',        21 => 'notify_bl',
            22 => 'temperature',     23 => 'weather',
            24 => 'charge_mode',
        ];

        // Load defaults from functions.php if available
        if (function_exists('sessionDefaults')) {
            $session = sessionDefaults();
        } else {
            require_once $dir . '/functions.php';
            $session = sessionDefaults();
        }

        foreach ($mapping as $oldIndex => $newKey) {
            if (isset($parts[$oldIndex]) && $parts[$oldIndex] !== '') {
                $session[$newKey] = $parts[$oldIndex];
            }
        }

        $session['bl_action_done'] = ($session['bl_action_done'] === 'Y');
        $session['is_charging']    = ($session['is_charging'] === 'Y');

        if (copy($sessionPath, $sessionPath . '.bak')) {
            $json = json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (file_put_contents($sessionPath, $json)) {
                echo "migrated (backup: session.bak)\n";
                $migrated[] = 'session';
            } else {
                echo "ERROR: Could not write new session file.\n";
                $errors[] = 'session';
            }
        } else {
            echo "ERROR: Could not create backup.\n";
            $errors[] = 'session';
        }
    }
}

// ─── Summary ────────────────────────────────────────────────────────

echo "\n=== Summary ===\n";
if (!empty($migrated)) {
    echo "Migrated: " . implode(', ', $migrated) . "\n";
}
if (!empty($errors)) {
    echo "Errors: " . implode(', ', $errors) . "\n";
    echo "Please fix the errors and run migration.php again.\n";
} elseif (empty($migrated)) {
    echo "Nothing to migrate — all files are already in the new format.\n";
} else {
    echo "Migration complete. You can now delete migration.php.\n";
}
