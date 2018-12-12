<?php
require_once '_inc.php';
use Ares333\Curl\Toolkit;
$toolkit = new Toolkit();
$toolkit->setCurl();
$curl = $toolkit->getCurl();
$curl->maxThread = 1;
$curl->onTask = function ($curl) {
    static $i = 0;
    if ($i >= 50) {
        return;
    }
    $url = 'http://www.baidu.com';
    $curl->add(
        array(
            'opt' => array(
                CURLOPT_URL => $url . '?wd=' . $i ++
            )
        ));
};
$curl->start();