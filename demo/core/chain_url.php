<?php
require_once '../vendor/autoload.php';
use Ares333\CurlMulti\Core;
$curl = new Core ();
$url = 'http://baidu.com';
$curl->add ( array (
		'url' => $url
), 'cb1' );
// start spider
$curl->start ();
function cb1($r, $args) {
	echo $r ['info'] ['url'] . " finished\n";
	global $curl;
	$curl->add ( array (
			'url' => 'http://bing.com'
	), 'cb2' );
	echo "http://bing.com added\n";
}
function cb2($r, $args) {
	echo $r ['info'] ['url'] . " finished\n";
}