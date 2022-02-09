<?php 
require "includes/FacebookBot.php";
require "modules/Auth.php";
require "modules/Menu.php";

date_default_timezone_set('Asia/Jakarta');

echo "FacebookBot by FaanTeyki".PHP_EOL;
echo "Version: 1.0".PHP_EOL;		
echo "LastUpdate: 9 Februari 2022".PHP_EOL.PHP_EOL;		

$Auth = new Auth();
$login = $Auth->Start();

// after login > select menu
$Menu = new Menu();
$select = $Menu->Start($login);