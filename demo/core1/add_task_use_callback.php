<?php
require '../../CurlMulti/Core.php';
require './inc/cb_info.php';
$curl = new CurlMulti_Core ();
$curl->cbInfo = 'cbInfo';
$curl->cbTask = array (
		'cbTask',
		'this is param for cbTask'
);
$curl->start ();
function cbTask($param) {
	static $i = 0;
	global $curl;
	if ($i ++ > 100) {
		return null;
	} else {
		$curl->add ( array (
				'url' => 'http://www.baidu.com?wd=' . $i
		) );
	}
}