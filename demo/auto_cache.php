<?php
require_once '_inc.php';
use Ares333\Curl\Toolkit;
$toolkit = new Toolkit();
$toolkit->setCurl();
$curl = $toolkit->getCurl();
$curl->maxThread = 2;
$curl->cache['enable'] = true;
$curl->cache['dir'] = __DIR__ . '/output/cache';
$url = 'http://www.baidu.com';
for ($i = 0; $i < 20; $i ++) {
    $curl->add(
        array(
            'opt' => array(
                CURLOPT_URL => $url . '?wd=' . $i
            )
        ));
}
$curl->start();