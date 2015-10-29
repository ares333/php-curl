<?php
include '../../CurlMulti/Core.php';
include '../../CurlMulti/Base.php';
include '../../CurlMulti/Base/Clone.php';
include '../../phpQuery.php';
$url = array (
		'http://www.laruence.com/manual' => array (
				'/' => null
		)
);
$dir = __DIR__ . '/static';
$cacheDir = __DIR__ . '/cache';
if (! file_exists ( $dir )) {
	mkdir ( $dir );
}
if (! file_exists ( $cacheDir )) {
	mkdir ( $cacheDir );
}
$clone = new CurlMulti_Base_Clone ( $url, $dir );
$clone->overwrite = true;
$clone->getCurl ()->maxThread = 3;
$clone->getCurl ()->cache ['enable'] = true;
$clone->getCurl ()->cache ['enableDownload'] = true;
$clone->getCurl ()->cache ['dir'] = $cacheDir;
$clone->getCurl ()->cache ['compress'] = true;
$clone->start ();