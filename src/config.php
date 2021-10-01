<?php
//Name for your Renault Zoe (shows as heading)
$zoename = 'Renault Zoe';

//Your Renault Zoe model: 1 for Ph1 or 2 for Ph2
$zoeph = 1;

//Login to My Renault
// Moved to URL

//VIN of your Renault Zoe
// Moved to URL

//Save data in database.csv: Y for yes or N for no
$save_in_db = 'N';

//If battery level is reached:
//Send mail: Y for yes or N for no
$mail_bl = 'N';
//Execute command, e.g. 'bash hello.sh'
$exec_bl = '';
//Activate charging schedule to stop charging: Y for yes or N for no
$cmon_bl = 'N';

//If charging is finished:
//Send mail: Y for yes or N for no
$mail_csf = 'N';
//Execute command, e.g. 'bash hello.sh'
$exec_csf = '';

//Hide charging schedule info and commands: Y for yes or N for no
$hide_cm = 'N';

//Using cron: Request index.php?cron or php ../index.php cron
//Minimum time interval in minutes between two requests if the car isn't charging
$cron_ncs = 60;
//Minimum time interval in minutes between two requests if the car charging
$cron_acs = 15;

//openweathermap.org API key for requesting weather data (only Ph2)
//More information: https://openweathermap.org/appid
$weather_api_key = '';

//Registration country of your Renault Zoe
//Deutschland: DE
//Österreich: AT
//Sverige: SE
//United Kingdom: GB
$country = 'EN';

//ABRP Generic Token
//More information: https://abetterrouteplanner.com/
$abrp_token = '';
//ABRP model
//Ph1
//22 kWh Q210: 'renault:zoe:q210:22:other'
//22 kWh R240: 'renault:zoe:r240:22:other'
//Z.E. 40 Q90: 'renault:zoe:q90:40:other'
//Z.E. 40 R110: 'renault:zoe:r110:40:other'
//Z.E. 40 R90: 'renault:zoe:r90:40:other'
//Ph2
//Z.E. 40 R110: 'renault:zoe:20:40:r110:noccs'
//Z.E. 40 R110 with CCS: 'renault:zoe:20:40:r110'
//Z.E. 50 R110: 'renault:zoe:20:52:r110'
//Z.E. 50 R135: 'renault:zoe:20:52:r135'
$abrp_model = '';
?>
