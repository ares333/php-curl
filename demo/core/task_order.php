<?php
// execute in order of task added
require_once '../vendor/autoload.php';
use Ares333\CurlMulti\Core;
$curl = new Core ();
$curl->maxThread = 1;
$curl->taskPoolType = 'queue';
$url = 'http://www.baidu.com';
for($i = 0; $i < 10; $i ++) {
	$curl->add ( array (
			'url' => $url . '?wd=' . $i,
			'args' => array (
					'i' => $i
			)
	), 'cbProcess' );
	echo "$i added\n";
}
$curl->start ();
function cbProcess($r, $args) {
	echo $args ['i'] . " finished\n";
}