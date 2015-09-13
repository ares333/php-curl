<?php
require '../../CurlMulti/Core.php';
$curl = new CurlMulti_Core ();
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