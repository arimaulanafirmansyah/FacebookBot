<?php 
require "includes/FacebookBot.php";
require "modules/Auth.php";
require "modules/Menu.php";

date_default_timezone_set('Asia/Jakarta');

echo "FacebookBot by FaanTeyki".PHP_EOL;
echo "Update By: Ari Maulana Firmansyah".PHP_EOL;		
echo "LastUpdate: 27 Juni 2023".PHP_EOL.PHP_EOL;		

$Auth = new Auth();
$login = $Auth->Start();

// after login > select menu
$Menu = new Menu();
$select = $Menu->Start($login);