<?php
require '../../CurlMulti/Core.php';
$url = 'http://badurl';
$curl = new CurlMulti_Core ();
$curl->opt [CURLOPT_TIMEOUT] = 1;
$curl->add ( array (
		'url' => $url
), null, 'cbFail' );
// start spider
$curl->start ();
function cbFail($err, $args) {
	print_r ( $err );
}