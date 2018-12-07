<?php
require_once '_inc.php';
use Ares333\Curl\Toolkit;
$toolkit = new Toolkit();
$toolkit->setCurl();
$curl = $toolkit->getCurl();
$curl->onInfo = null;
$url = 'http://baidu.com';
$curl->add(array(
    'opt' => array(
        CURLOPT_URL => $url
    )
), 'cb1');
echo "add $url\n";
$curl->start();

function cb1($r, $args)
{
    echo "finish " . $r['info']['url'] . "\n";
    $url = 'http://bing.com';
    $r['curl']->add(
        array(
            'opt' => array(
                CURLOPT_URL => $url
            )
        ), 'cb2');
    echo "add $url\n";
}

function cb2($r, $args)
{
    echo "finish " . $r['info']['url'] . "\n";
}