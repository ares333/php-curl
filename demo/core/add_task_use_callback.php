<?php
require '../../CurlMulti/Core.php';
$curl = new CurlMulti_Core ();
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
	echo $i.' tasks added, cbTask called ' . ++ $j . " times\n";
}