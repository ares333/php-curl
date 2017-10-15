<?php
require_once '_inc.php';
use Ares333\Curlmulti\Curl;
$url = array(
    'http://baidu.com',
    'http://cn.bing.com'
);
$curl = new Curl();
foreach ($url as $v) {
    $curl->add(
        array(
            'opt' => array(
                CURLOPT_URL => $v
            ),
            'args' => array(
                'test' => 'this is user arg for ' . $v
            )
        ), 'cbProcess');
}
// start spider
$curl->start();

function cbProcess($r, $args)
{
    echo "success, url=" . $r['info']['url'] . "\n";
    unset($r['body']);
    print_r($r);
    echo "args:\n";
    print_r($args);
}