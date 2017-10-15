<?php
require '_inc.php';
use Ares333\Curlmulti\Curl;
$curl = new Curl();
$url = 'http://www.baidu.com/img/bd_logo1.png';
$file = __DIR__ . '/output/download.png';
// $fp must be closed in onProcess()
$fp = fopen($file, 'w');
$curl->add(
    array(
        'opt' => array(
            CURLOPT_URL => $url,
            CURLOPT_FILE => $fp,
            CURLOPT_HEADER => false
        ),
        'args' => array(
            'file' => $file,
            'fp' => $fp
        )
    ), 'onProcess')->start();

function onProcess($r, $args)
{
    fclose($args['fp']);
    echo "download finished successfully, file=$args[file]\n";
}