<?php
//Name für den Renault Zoe
$zoename = 'Renault Zoe';

//Zoe-Modell (1 für Ph1 und 2 für Ph2)
$zoeph = 1;

//Login-Daten My Renault
$username = 'meine@emailadres.se';
$password = 'MeinPasswort';

//VIN des Renault Zoe
$vin = 'VF1...';

//Datenspeicherung (Y für ja und N für nein)
$save_in_db = 'N';

//Mailversand, wenn Akkustand erreicht (Y für ja und N für nein)
$mail_bl = 'N';

//Mailversand, wenn Ladevorgang beendet (Y für ja und N für nein)
$mail_csf = 'N';

//Nutzung von Cron: Aufruf per index.php?cron oder php ../index.php cron
//Minimaler Zeitabstand in Minuten zwischen zwei Aufrufen, wenn nicht geladen wird
$cron_ncs = 60;
//Minimaler Zeitabstand in Minuten zwischen zwei Aufrufen während eines Ladevorgangs
$cron_acs = 15;

//Wetter API Schlüssel (openweathermap.org) für Wetterabfrage bei Ph2
//Infos zur Registrierung: https://openweathermap.org/appid
$weather_api_key = '';

//Land
$country = 'DE';

// Gigya API Schlüssel (länderabhängig)

//Deutschland
$gigya_api = '3_7PLksOyBRkHv126x5WhHb-5pqC1qFR8pQjxSeLB6nhAnPERTUlwnYoznHSxwX668';

//Österreich
//$gigya_api = '3__B4KghyeUb0GlpU62ZXKrjSfb7CPzwBS368wioftJUL5qXE0Z_sSy0rX69klXuHy';

//Schweden
//$gigya_api = '3_EN5Hcnwanu9_Dqot1v1Aky1YelT5QqG4TxveO0EgKFWZYu03WkeB9FKuKKIWUXIS';

//Kamereon API Schlüssel
$kamereon_api = 'oF09WnKqvBDcrQzcW1rJNpjIuy7KdGaB';
?>