<?php
require_once '../vendor/autoload.php';
use Ares333\CurlMulti\Core;
use Ares333\CurlMulti\Base;
$curl = new Core ();
$curl->cbInfo = array (
		new Base (),
		'cbCurlInfo'
);
$curl->maxThread = 1;
$url = 'http://www.baidu.com';
for($i = 0; $i < 100; $i ++) {
	$curl->add ( array (
			'url' => $url . '?wd=' . $i
	) );
}
$curl->start ();