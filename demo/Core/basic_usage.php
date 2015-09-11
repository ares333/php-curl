<?php
require '../../CurlMulti/Core.php';
$url = array (
		'http://baidu.com',
		'http://bing.com'
);
$curl = new CurlMulti_Core ();
foreach ( $url as $v ) {
	$curl->add ( array (
			'url' => $v
	), 'cbProcess' );
}
// start spider
$curl->start ();
function cbProcess($r, $args) {
	echo "success, url=" . $r ['info'] ['url'] . "\n";
	print_r ( array_keys( $r ) );
	print_r ( $args );
}