<?php
require '../../CurlMulti/Core.php';
$curl = new CurlMulti_Core ();
$url = 'http://www.baidu.com/img/bd_logo1.png';
$file = __DIR__ . '/baidu.png';
$curl->add ( array (
		'url' => $url,
		'file' => __DIR__ . '/baidu.png',
		'args' => array (
				'filePath' => $file
		)
), 'cbProcess' );
// start spider
$curl->start ();
function cbProcess($r, $args) {
	echo "download finished successfully, file=$args[filePath]\n";
}