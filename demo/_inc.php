<?php
require_once 'vendor/autoload.php';
$dir = array(
    __DIR__ . '/output',
    __DIR__ . '/output/clone',
    __DIR__ . '/output/cache'
);
foreach ($dir as $v) {
    if (! is_dir($v)) {
        mkdir($v);
    }
}