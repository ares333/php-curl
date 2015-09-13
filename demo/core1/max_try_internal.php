<?php
require '../../CurlMulti/Core.php';
$url = 'http://badurl';
$curl = new CurlMulti_Core ();
// timeout will occur 10 times
$curl->maxTry = 10;
$curl->opt [CURLOPT_CONNECTTIMEOUT] = 1;
$curl->opt [CURLOPT_TIMEOUT] = 1;
$curl->cbFail = 'cbFail';
$curl->add ( array (
		'url' => $url
) );
// start spider
$curl->start ();
function cbFail($err, $args) {
	print_r ( $err ['error'] );
}
