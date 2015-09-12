<?php
require '../../CurlMulti/Core.php';
$curl = new CurlMulti_Core ();
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