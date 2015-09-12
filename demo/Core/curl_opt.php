<?php
require '../../CurlMulti/Core.php';
$curl = new CurlMulti_Core ();
$curl->opt [CURLOPT_RETURNTRANSFER] = false;
$url = 'http://www.baidu.com';
$curl->add ( array (
		'url' => $url,
		// this will override $curl->opt[CURLOPT_RETURNTRANSFER]
		'opt' => array (
				CURLOPT_RETURNTRANSFER => true
		)
), function ($r, $args) {
	echo "content length: " . strlen ( $r ['content'] );
} );
$curl->start ();