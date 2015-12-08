<?php
require_once '../vendor/autoload.php';
use Ares333\CurlMulti\Core;
$url1 = 'http://badurl1';
$url2 = 'http://badurl2';
$curl = new Core ();
$curl->maxTry = 1;
$curl->opt [CURLOPT_CONNECTTIMEOUT] = 1;
$curl->opt [CURLOPT_TIMEOUT] = 1;
// cbFail golbal
$curl->cbFail = 'cbFailGlobal';
// cbFail for individual task
$curl->add ( array (
		'url' => $url1
), null, 'cbFailTask' )->add ( array (
		'url' => $url2
) )->start ();
function cbFailTask($err, $args) {
	echo 'Task Fail: ' . $err ['info'] ['url'] . "\n";
	print_r ( $err ['error'] );
}
function cbFailGlobal($err, $args) {
	echo 'Global Fail: ' . $err ['info'] ['url'] . "\n";
	print_r ( $err ['error'] );
}