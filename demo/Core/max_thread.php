<?php
require '../../CurlMulti/Core.php';
require './inc/cb_info.php';
$curl = new CurlMulti_Core ();
$curl->cbInfo = 'cbInfo';
$curl->maxThread = 10;
$curl->maxThreadType ['html'] = 2;
$curl->maxThreadType ['image'] = 5;
$url1 = 'http://www.baidu.com';
$url2 = 'http://www.baidu.com/img/bd_logo1.png';
for($i = 0; $i < 100; $i ++) {
	$curl->add ( array (
			'url' => $url1 . '?wd=' . $i,
			'ctl' => array (
					'type' => 'html'
			)
	) );
	$curl->add ( array (
			'url' => $url2 . '?i=' . $i,
			'ctl' => array (
					'type' => 'image'
			)
	) );
}
$curl->start ();