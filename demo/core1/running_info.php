<?php
require '../../CurlMulti/Core.php';
require './inc/cb_info.php';
$curl = new CurlMulti_Core ();
$curl->cbInfo = 'cbInfo';
$curl->maxThread = 1;
$url = 'http://www.baidu.com';
for($i = 0; $i < 100; $i ++) {
	$curl->add ( array (
			'url' => $url . '?wd=' . $i
	) );
}
$curl->start ();