<?php
//Name for your Renault Zoe/MeganE (shows as heading)
$zoename = 'Renault EV';

//Your Renault model:
//1 for Zoe Ph1
//2 for Zoe Ph2 and MeganE
$zoeph = 1;

//Login to My Renault
$username = 'your@mailadress.com';
$password = 'My_password';

//VIN of your Renault
$vin = 'VF1...';

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

//Use Google Maps or OpenStreetMap for Ph2
//'google' for Google Maps or 'osm' for OpenStreetMap
$map_provider = 'google';

//openweathermap.org API key for requesting weather data (only Ph2)
//More information: https://openweathermap.org/appid
$weather_api_key = '';

//Registration country of your Renault
//Deutschland: DE
//Österreich: AT
//Italia: IT
//Sverige: SE
//United Kingdom: GB
$country = 'GB';

//ABRP Generic Token
//More information: https://abetterrouteplanner.com/
$abrp_token = '';
//ABRP model
//Ph1
//Zoe 22 kWh Q210: 'renault:zoe:q210:22:other'
//Zoe 22 kWh R240: 'renault:zoe:r240:22:other'
//Zoe 40 Q90: 'renault:zoe:q90:40:other'
//Zoe 40 R110: 'renault:zoe:r110:40:other'
//Zoe 40 R90: 'renault:zoe:r90:40:other'
//Ph2
//Zoe 40 R110: 'renault:zoe:20:40:r110:noccs'
//Zoe 40 R110 with CCS: 'renault:zoe:20:40:r110'
//Zoe 50 R110: 'renault:zoe:20:52:r110'
//Zoe 50 R135: 'renault:zoe:20:52:r135'
//MeganE EV40: 'renault:megane:22:40'
//MeganE EV60: 'renault:megane:22:60'
//
$abrp_model = '';
?>
