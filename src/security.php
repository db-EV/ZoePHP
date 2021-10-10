<?php
// Please add this string to the end of each curl call:

// '?pass=miapasssegretissima&username=' . $username . '&password=' . $password . '&vin=' . $vin

if (!isset($_GET['pass'])) {
    die('Not authorized');
} else {
	if ($_GET['pass'] != 'miapasssegretissima') {
		die('Not authorized');
	}
}



if (!isset($_GET['username'])) {
    die('Please provide username for your MyRenault account.');
} else {
	$username = $_GET['username'];
}




if (!isset($_GET['password'])) {
    die('Please provide password for your MyRenault account.');
} else {
	$password = $_GET['password'];
}

if (!isset($_GET['vin'])) {
    die('Please provide VIN of your vehicle (it\'s in your MyRenault app)');
} else {
	$vin = $_GET['vin'];
}

?>