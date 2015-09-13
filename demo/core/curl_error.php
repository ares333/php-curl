<?php
require '../../CurlMulti/Core.php';
$url1 = 'http://badurl1';
$url2 = 'http://badurl2';
$curl = new CurlMulti_Core ();
$curl->maxTry=1;
$curl->opt [CURLOPT_CONNECTTIMEOUT] = 1;
$curl->opt [CURLOPT_TIMEOUT] = 1;
// cbFail for individual task
$curl->add ( array (
		'url' => $url1
), null, 'cbFailTask' );
// cbFail golbal
$curl->cbFail = 'cbFailGlobal';
$curl->add ( array (
		'url' => $url2
) );
// start spider
$curl->start ();
function cbFailTask($err, $args) {
	echo $err ['info'] ['url'] . "\n";
	print_r ( $err ['error'] );
}
function cbFailGlobal($err, $args) {
	echo $err ['info'] ['url'] . "\n";
	print_r ( $err ['error'] );
}