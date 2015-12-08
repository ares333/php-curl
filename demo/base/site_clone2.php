<?php
require_once '../vendor/autoload.php';
use Ares333\CurlMulti\AutoClone;
$url = array (
		'http://yamlcss.meezhou.com' => array (
				'/' => array (
						'depth' => 1
				),
				'documentation' => null,
				'blog' => null,
				'download' => null,
				'category' => null
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
$clone = new AutoClone ( $url, $dir );
$clone->overwrite = true;
$clone->getCurl ()->maxThread = 3;
$clone->getCurl ()->cache ['enable'] = true;
$clone->getCurl ()->cache ['enableDownload'] = true;
$clone->getCurl ()->cache ['dir'] = $cacheDir;
$clone->getCurl ()->cache ['compress'] = true;
$clone->start ();