 <?php
require_once '_inc.php';
use Ares333\Curl\Toolkit;
$curl = (new Toolkit())->getCurl();
$curl->maxThread = 1;
$url = 'http://www.baidu.com';
for ($i = 0; $i < 50; $i ++) {
    $curl->add(
        array(
            'opt' => array(
                CURLOPT_URL => $url . '?wd=' . $i
            )
        ));
}
$curl->start();