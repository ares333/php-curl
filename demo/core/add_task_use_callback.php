<?php
require_once '../vendor/autoload.php';
use Ares333\CurlMulti\Core;
$curl = new Core ();
$curl->maxThread = 3;
$curl->cbTask = array (
		'cbTask',
		'this is param for cbTask'
);
$curl->start ();
function cbTask($param) {
	static $i = 0, $j = 0;
	global $curl;
	$count = 10;
	if ($i < $count) {
		$curl->add ( array (
				'url' => 'http://www.baidu.com?wd=' . $i
		) );
		$i ++;
		if ($i == $count) {
			$curl->cbTask = null;
		}
	}
	echo $i . ' tasks added, cbTask called ' . ++ $j . " times\n";
}