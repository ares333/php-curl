<?php
require_once '_inc.php';
use Ares333\Curl\Curl;
use Ares333\Curl\Toolkit;
$curl = new Curl();
$toolkit = new Toolkit();
$curl->onInfo = array(
    $toolkit,
    'onInfo'
);
$curl->maxThread = 2;
$url = 'http://www.baidu.com';
for ($i = 0; $i < 100; $i ++) {
    $curl->add(
        array(
            'opt' => array(
                CURLOPT_URL => $url . '?wd=' . $i
            )
        ));
}
$curl->start();