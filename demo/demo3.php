<?php
$url = 'http://www.laruence.com/manual';
$dir = __DIR__.'/manual';
$cacheDir = __DIR__.'/cache';
$clone = new CurlMulti_My_Clone ( $url, $dir );
$clone->overwrite = true;
$clone->getCurl ()->cache ['enable'] = true;
$clone->getCurl ()->cache ['enableDownload'] = true;
$clone->getCurl ()->cache ['dir'] = $cacheDir;
$clone->getCurl ()->cache ['compress'] = true;
$clone->start ();