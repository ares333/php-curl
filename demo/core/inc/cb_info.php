<?php
function cbInfo($info) {
	$all = $info ['all'];
	$cacheNum = $all ['cacheNum'];
	$taskPoolNum = $all ['taskPoolNum'];
	$finishNum = $all ['finishNum'];
	$speed = round ( $all ['downloadSpeed'] / 1024 ) . 'KB/s';
	$size = round ( $all ['downloadSize'] / 1024 / 1024 ) . "MB";
	$str = '';
	$str .= sprintf ( "speed:%-10s", $speed );
	$str .= sprintf ( 'download:%-10s', $size );
	$str .= sprintf ( 'cache:%-10dfinish:%-10d', $cacheNum, $finishNum );
	$str .= sprintf ( 'taskPool:%-10d', $taskPoolNum );
	foreach ( $all ['taskRunningNumType'] as $k => $v ) {
		$str .= sprintf ( 'running' . $k . ':%-10d', $all ['taskRunningNumType'] [$k] );
	}
	$str .= sprintf ( 'running:%-10d', $all ['taskRunningNumNoType'] );
	if (PHP_OS == 'Linux') {
		// \33[k empty the full line
		$str = "\r\33[K" . trim ( $str );
	} else {
		$str = "\r" . $str;
	}
	echo "\r" . $str;
}