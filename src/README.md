# Renault unofficial PHP dashboard for EVs and PHEVs
Unofficial PHP client for the Renault Zoe - fork from https://github.com/db-EV/ZoePHP/network/members . 

Started 01/OCT/2021

## New features (in progress)
* Security check 1: you must specify a password to use the script
* Security check 2: you must provide Renault credentials in the URL, they are no more saved into config.php
* Charge history: lists all charge sessions, grouped by day or by month, in table format for easy export to Excel

## To do
* HVAC history
* Trips history
* Various undocumented commands found here: https://github.com/mitchellrj/kamereon-python/blob/146904802301aa0b0008e2bdb3a88ed10ff50acf/kamereon/kamereon.py

---------------

## Requirements
* Renault car with active data subscription
* Webserver with PHP 5.3 (or newer) and cURL installed
* Write permissions for the script in its own folder

## Usage instructions
* If you work from GitHub, download config.php and make sure it is in .gitignore (should be already).
* Adjust the settings in config.php before you run the script for the first time.
* When calling the script for the first time it creates a "session" file. This file is used for caching your account id, token, car data and so on. Please delete this file after every update of the script.
* When you activate the database function the script will create "database.csv" as database file for all data records. You can import this file into Microsoft Excel, for example. For saving your car's data regulary you can run the script periodically, for example with cron.
* If it wasn't possible to receive new data from Renault you will see a notice together with cached data.
* You can activate two simple mail notifications when running the script periodically: When 1) a specified battery level is reached and/or 2) charging is finished. You can even execute commands when these events are triggered.
* When the battery level above is reached you can also activate the schedule mode to stop charging.
* When you call the script periodically using "index.php?cron" or "php index.php cron" you can set how often the Renault API is called (charging/not charging), regardless how often the script itself is called.
* When you call the script periodically you can also submit live data to ABRP. Just add your ABRP generic token and car model in the config.php.
* Thanks to @ToKen for the openweathermap.org integration for Ph2-Zoes! If you want to use this feature you need an API key from openweathermap.org.
* Give a big hand also to [Muscat's OxBlog](https://muscatoxblog.blogspot.com/2019/07/delving-into-renaults-new-api.html) for decrypting the Renault API.
* For security reasons I recommend to secure the script with basic authentication or other access restrictions.

## Screenshots
Ph1 | Ph2
------------ | -------------
![Screenshot Ph1](screenshot_ph1.png) | ![Screenshot Ph2](screenshot_ph2.png)
