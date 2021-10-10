<?php
// Please add this string to the end of each curl call:

// '?pass=miapasssegretissima&username=' . $username . '&password=' . $password . '&vin=' . $vin

if (!isset($_GET['pass'])) {
		echo "Use this url:<br>";
		echo "https://jumpjack.altervista.org/myrenault-debug/php/index.php?pass=miapasssegretissima&username=EMAIL&password=PASSWORD&vin=VIN&showResponse=false<br>";
		echo "(use showResponse=true for debugging purposes)<br><br><b>";
    die('Not authorized');
} else {
	if ($_GET['pass'] != 'miapasssegretissima') {
		echo "Use this url:<br>";
		echo "https://jumpjack.altervista.org/myrenault-debug/php/index.php?pass=miapasssegretissima&username=EMAIL&password=PASSWORD&vin=VIN&showResponse=false<br>";
		echo "(use showResponse=true for debugging purposes)<br><br><b>";
		die('Not authorized');
	}
}



if (!isset($_GET['username'])) {
		echo "Use this url:<br>";
		echo "https://jumpjack.altervista.org/myrenault-debug/php/index.php?pass=miapasssegretissima&username=EMAIL&password=PASSWORD&vin=VIN&showResponse=false<br>";
		echo "(use showResponse=true for debugging purposes)<br><br><b>";
    die('Please provide username for your MyRenault account.');
} else {
	$username = $_GET['username'];
}




if (!isset($_GET['password'])) {
		echo "Use this url:<br>";
		echo "https://jumpjack.altervista.org/myrenault-debug/php/index.php?pass=miapasssegretissima&username=EMAIL&password=PASSWORD&vin=VIN&showResponse=false<br>";
		echo "(use showResponse=true for debugging purposes)<br><br><b>";
    die('Please provide password for your MyRenault account.');
} else {
	$password = $_GET['password'];
}

if (!isset($_GET['vin'])) {
		echo "Use this url:<br>";
		echo "https://jumpjack.altervista.org/myrenault-debug/php/index.php?pass=miapasssegretissima&username=EMAIL&password=PASSWORD&vin=VIN&showResponse=false<br>";
		echo "(use showResponse=true for debugging purposes)<br><br><b>";
    die('Please provide VIN of your vehicle (it\'s in your MyRenault app)');
} else {
	$vin = $_GET['vin'];
	echo "VIN=" . $vin . "<br>";
}

?>