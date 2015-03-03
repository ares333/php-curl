<?php
$urls = array ();
for($i = 0; $i < 100; $i ++) {
	$urls [] = 'http://localhost/' . $i . '.html';
}
$dir = __DIR__ . '/cache';
$curl = new CurlMulti_Core ();
$curl->opt [CURLOPT_TIMEOUT] = 30;
$curl->maxThread = 10;
$curl->maxTry = 3;
$curl->cache = array (
		'enable' => true,
		'dir' => $dir,
		'expire' => 3600 
);
$cbFailGlobal = 'callbackFail';
$cbFailTask = 'callbackFailTask';
$curl->cbFail = $cbFailGlobal;
foreach ( $urls as $k => $v ) {
	$task = array (
			'url' => $v,
			'args' => array (
					'page' . $k 
			) 
	);
	$curl->add ( $task, 'callback1', $cbFailTask );
}
// download task with task CURLOPT_*,callback can be ommited
$curl->add ( array (
		'url' => 'https://www.kernel.org/pub/linux/kernel/v3.x/linux-3.19.tar.xz',
		'file' => __DIR__ . '/linux-3.19.tar.xz',
		'opt' => array (
				CURLOPT_TIMEOUT => 600 
		) 
) );
// start the loop
$curl->start ();
function callback1($r, $args) {
	global $curl;
	// you can call $curl->add() anywhere
	$curl->add ( array (
			'url' => 'https://gcc.gnu.org/' 
	), 'callback2' );
}
function callback2($r, $args) {
}
function callbackFailTask($error, $args) {
}
function callbackFail($error, $args) {
}