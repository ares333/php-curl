<?php
require_once '../vendor/autoload.php';
use Ares333\CurlMulti\Core;
$url = array ();
$curl = new Core ();
$curl->start ( function () {
	static $i = 0;
	echo $i ++ . "\n";
	if ($i >= 7) {
		return false;
	}
	sleep ( 1 );
	return true;
} );