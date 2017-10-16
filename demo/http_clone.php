<?php
require_once '_inc.php';
use Ares333\Curlmulti\HttpClone;

$dir = __DIR__ . '/output/clone';
$cacheDir = __DIR__ . '/output/cache';
global $argv;
if (! isset($argv[1])) {
    $argv[1] = '1';
}
$dumpFile = __DIR__ . '/output/clone/dump' . $argv[1];
if (is_file($dumpFile)) {
    $clone = unserialize(file_get_contents($dumpFile));
} else {
    $clone = new HttpClone($dir);
    $clone->getCurl()->onEvent = function () {
        pcntl_signal_dispatch();
    };
    $clone->getCurl()->opt[CURLOPT_CONNECTTIMEOUT] = 3;
    $clone->getCurl()->opt[CURLOPT_ENCODING] = 'gzip,deflate';
    $clone->getCurl()->cache['enable'] = true;
    $clone->getCurl()->cache['enableDownload'] = true;
    $clone->getCurl()->cache['dir'] = $cacheDir;
    $clone->getCurl()->cache['compress'] = 6;
    $clone->expire = 0;
    switch ($argv[1]) {
        case '1':
            $clone->getCurl()->maxThread = 1;
            $clone->getCurl()->opt[CURLOPT_TIMEOUT] = 5;
            $clone->add('http://www.laruence.com/manual/');
            break;
        case '2':
            $clone->getCurl()->maxThread = 2;
            $clone->getCurl()->opt[CURLOPT_TIMEOUT] = 30;
            $clone->add('http://www.handubaby.com', 5);
            break;
    }
    pcntl_signal(SIGINT,
        function () use ($clone, $dumpFile) {
            $clone->getCurl()->serialize(
                function () use ($clone, $dumpFile) {
                    file_put_contents($dumpFile, serialize($clone));
                    exit(0);
                });
        });
}
$clone->start();
if (is_file($dumpFile)) {
    unlink($dumpFile);
}