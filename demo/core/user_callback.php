<?php
//cbUser is called whenever curl has network traffic
require_once '../vendor/autoload.php';
use Ares333\CurlMulti\Core;
$curl = new Core ();
$curl->maxThread = 1;
$curl->cbUser = 'cbUser';
$url = 'http://www.baidu.com';
for($i = 0; $i < 3; $i ++) {
	$curl->add ( array (
			'url' => $url . '?wd=' . $i
	) );
}
$curl->start ();
function cbUser() {
	static $i = 0;
	echo $i ++ . "\n";
}