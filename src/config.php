<?php
/**
 * Configuration for Renault EV Dashboard
 * 
 * Edit this file to match your vehicle and preferences.
 */

// Display name (shown as heading)
$zoename = 'Renault EV';

// Renault model: 1 = Zoe Ph1, 2 = Zoe Ph2 / MeganE
$zoeph = 1;

// My Renault login credentials
$username = 'your@mailadress.com';
$password = 'My_password';

// Vehicle Identification Number
$vin = 'VF1...';

// Registration country: DE, AT, IT, SE, GB
$country = 'GB';

// Timezone mapping per country
$timezones = [
    'DE' => 'Europe/Berlin',
    'AT' => 'Europe/Vienna',
    'IT' => 'Europe/Rome',
    'SE' => 'Europe/Stockholm',
    'GB' => 'Europe/London',
];
$timezone = $timezones[$country] ?? 'Europe/London';

// Save data in database.csv: true / false
$save_in_db = false;

// Notifications when battery level is reached
$mail_bl = false;       // Send mail
$exec_bl = '';          // Execute command, e.g. 'bash hello.sh'
$cmon_bl = false;       // Activate charging schedule to stop charging

// Notifications when charging is finished
$mail_csf = false;      // Send mail
$exec_csf = '';         // Execute command

// Hide charging schedule info and commands
$hide_cm = false;

// Cron settings (interval in minutes)
$cron_ncs = 60;         // Interval when NOT charging
$cron_acs = 15;         // Interval when charging

// Map provider for Ph2: 'google' or 'osm'
$map_provider = 'google';

// OpenWeatherMap API key (Ph2 only)
// See: https://openweathermap.org/appid
$weather_api_key = '';

// ABRP integration
// See: https://abetterrouteplanner.com/
$abrp_token = '';
$abrp_model = '';
// ABRP model identifiers:
// Ph1: renault:zoe:q210:22:other, renault:zoe:r240:22:other,
//       renault:zoe:q90:40:other, renault:zoe:r110:40:other, renault:zoe:r90:40:other
// Ph2: renault:zoe:20:40:r110:noccs, renault:zoe:20:40:r110,
//       renault:zoe:20:52:r110, renault:zoe:20:52:r135
// MeganE: renault:megane:22:40, renault:megane:22:60
