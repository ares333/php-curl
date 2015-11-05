<?php
require '../../CurlMulti/Core.php';
$url = array ();
$curl = new CurlMulti_Core ();
$curl->start ( function () {
	static $i = 0;
	echo $i ++ . "\n";
	if ($i >= 3) {
		return false;
	}
	sleep ( 1 );
	return true;
} );