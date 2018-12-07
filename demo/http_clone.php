<?php
use Ares333\Curl\HttpClone;
require_once '_inc.php';
$dir = __DIR__ . '/output/clone';
$cacheDir = __DIR__ . '/output/cache';
global $argv;
if (! isset($argv[1])) {
    $argv[1] = '1';
}

class HttpCloneDemo extends HttpClone
{

    public $blockedFileRemoveCache = true;

    function onProcess($r, $args)
    {
        // Check invalid images.Url may be block by firewall.
        $checkExt = array(
            'jpg',
            'gif',
            'png'
        );
        if ($this->blockedFileRemoveCache && in_array(pathinfo($r['info']['url'], PATHINFO_EXTENSION), $checkExt)) {
            if (false !== strpos($r['info']['content_type'], 'text')) {
                $r['info']['http_code'] = 403;
                if (isset($r['cacheFile']) && is_file($r['cacheFile'])) {
                    unlink($r['cacheFile']);
                }
            }
        }
        return parent::onProcess($r, $args);
    }
}
$clone = new HttpCloneDemo($dir);
$clone->setCurl();
$clone->getCurl()->opt[CURLOPT_CONNECTTIMEOUT] = 3;
$clone->getCurl()->opt[CURLOPT_ENCODING] = 'gzip,deflate';
$clone->getCurl()->cache['enable'] = true;
$clone->getCurl()->cache['expire'] = 86400 * 30;
$clone->getCurl()->cache['enableDownload'] = true;
$clone->getCurl()->cache['dir'] = $cacheDir;
$clone->getCurl()->cache['compress'] = 6;
$clone->expire = 0;
switch ($argv[1]) {
    case '1':
        $clone->getCurl()->maxThread = 1;
        $clone->getCurl()->opt[CURLOPT_TIMEOUT] = 5;
        $clone->add('http://www.laruence.com/manual/');
        $clone->blacklist = array(
            'http://img.tongji.linezing.com/728491/tongji.gif',
            'http://js.tongji.linezing.com/728491/tongji.js',
            'http://www.laruence.com/manual/css/images/dialog-information.png',
            'http://www.laruence.com/manual/css/images/dialog-warning.png'
        );
        break;
    case '2':
        $clone->getCurl()->maxThread = 2;
        $clone->getCurl()->opt[CURLOPT_TIMEOUT] = 30;
        $clone->download['video'] = true;
        $clone->add('http://www.handubaby.com', 5);
        break;
    case '3':
        $clone->getCurl()->maxThread = 2;
        $clone->getCurl()->opt[CURLOPT_TIMEOUT] = 30;
        $clone->add('http://www.bjszxx.cn/', 3);
        break;
}
$clone->start();
