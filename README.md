## [中文文档](README_CN.md "中文文档")

## About

Implemented by using php-curl internal io event with high performance,high universality,high extensibility which especially suitable for massive tasks and complex logic cases.

## Demand
PHP: >=5.3

## Install
```
composer require ares333/php-curl
```

## Features
1. Extremely low cpu and memory consumption with high performance(download 3000 html pages per second,download images with 1000Mbps on server with 1Gbps interface).
1. All curl options are exposed directly which enables high universality and high extensibility.
1. Api is very simple.
1. Support process disruption and resume from last running state.
1. Support dynamic tasks.
1. Support transparent file cache.
1. Support retry failed tasks automatically.
1. Support global config,task config,callback config on same format and priority is from low to high.
1. All configs can be changed on the fly and take effect immediately.

## Work Flow
Curl::add() add tasks to task pool.Curl::start() start the event loop and block.Events(onSuccess,onFail,onInfo,onTask...) will be triggered and callbacks will be called on the fly.The loop finished when all tasks finished.

## Tutorial
**basic**
```PHP
use Ares333\Curl\Curl;
$curl = new Curl();
$curl->add(
    array(
        'opt' => array(
            CURLOPT_URL => 'http://baidu.com',
            CURLOPT_RETURNTRANSFER => true
        ),
        'args' => 'This is user argument'
    ),
    function ($r, $args) {
        echo "Request success for " . $r['info']['url'] . "\n";
        echo "\nHeader info:\n";
        print_r($r['info']);
        echo "\nRaw header:\n";
        print_r($r['header']);
        echo "\nArgs:\n";
        print_r($args);
        echo "\n\nBody size:\n";
        echo strlen($r['body']) . ' bytes';
        echo "\n";
    });
$curl->start();
```
**file download**
```PHP
use Ares333\Curl\Curl;
$curl = new Curl();
$url = 'https://www.baidu.com/img/bd_logo1.png';
$file = __DIR__ . '/download.png';
// $fp is closed automatically on download finished.
$fp = fopen($file, 'w');
$curl->add(
    array(
        'opt' => array(
            CURLOPT_URL => $url,
            CURLOPT_FILE => $fp,
            CURLOPT_HEADER => false
        ),
        'args' => array(
            'file' => $file
        )
    ),
    function ($r, $args) {
        if($r['info']['http_code']==200) {
            echo "download finished successfully, file=$args[file]\n";
        }else{
            echo "download failed\n";
        }
    })->start();
```
**task callback**

Task can be added in task callback. See more details in Curl::$onTask.
```PHP
use Ares333\Curl\Toolkit;
use Ares333\Curl\Curl;
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
    /** @var Curl $curl */
    $curl->add(
        array(
            'opt' => array(
                CURLOPT_URL => $url . '?wd=' . $i ++
            )
        ));
};
$curl->start();
```
**running info**
```PHP
use Ares333\Curl\Toolkit;
use Ares333\Curl\Curl;
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
```
Run in cli and will output with following format:
```
SPD    DWN  FNH  CACHE  RUN  ACTIVE  POOL  QUEUE  TASK  FAIL  
457KB  3MB  24   0      3    3       73    0      100   0
```
'onInfo' callback will receive all information.The default callback only show part of it.
```
SPD：Download speed
DWN：Bytes downloaded
FNH：Task count which has finished
CACHE：Cache count which were used 
RUN：Task running count
ACTIVE：Task count which has IO activities
POOL：Task count in task pool
QUEUE：Task count which has finished and waiting for onSuccess callback to process
TASK：Task count has been added to the task pool
FAIL：Task count which has failed after retry.
```
**transparent cache**
```PHP
use Ares333\Curl\Toolkit;
use Ares333\Curl\Curl;
$curl = new Curl();
$toolkit = new Toolkit();
$curl->onInfo = array(
    $toolkit,
    'onInfo'
);
$curl->maxThread = 2;
$curl->cache['enable'] = true;
$curl->cache['dir'] = __DIR__ . '/output/cache';
if (! is_dir($curl->cache['dir'])) {
    mkdir($curl->cache['dir'], 0755, true);
}
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
```
Run the script second time and will output：
```
SPD  DWN  FNH  CACHE  RUN  ACTIVE  POOL  QUEUE  TASK  FAIL  
0KB  0MB  20   20     0    0       0     0      20    0
```
The result indicate that all tasks is using cache and there is no network activity.

**dynamic tasks**
```PHP
use Ares333\Curl\Curl;
$curl = new Curl();
$url = 'http://baidu.com';
$curl->add(array(
    'opt' => array(
        CURLOPT_URL => $url
    )
), 'cb1');
echo "add $url\n";
$curl->start();

function cb1($r)
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

function cb2($r)
{
    echo "finish " . $r['info']['url'] . "\n";
}
```
Output is as below:
```
add http://baidu.com
finish https://www.baidu.com/
add http://bing.com
finish http://cn.bing.com/
```
Finished url has '/' suffix because curl has processed the 3xx redirect automatically(Curl::$opt[CURLOPT_FOLLOWLOCATION]=true).
Curl::onTask should be used to deal with massive tasks.

## Curl (src/Curl.php Core functionality) 
```PHP
public $maxThread = 10
```
Max work parallels which can be change on the fly.

```PHP
public $maxTry = 3
```
Max retry before onFail event is triggered.

```PHP
public $opt = array ()
```
Global CURLOPT_\* which can be overwritten by task config.

```PHP
public $cache = array(
    'enable' => false,
    'compress' => 0, //0-9,6 is a good choice if you want use compress.
    'dir' => null, //Cache dir which must exists.
    'expire' => 86400,
    'verifyPost' => false //If http post will be part of cache id.
);
```
Global cache config.Cache id is mapped from url.The config can be overwritten by task config and onSuccess callback return value with same format.

```PHP
public $taskPoolType = 'queue'
```
stack or queue.

```PHP
public $onTask = null
```
Will be triggered when work parallels is less than Curl::$maxThread and task pool is empty.The callback parameter is current Curl instance.

```PHP
public $onInfo = null
```
Running state callback is triggered on IO events with max frequency 1/s.The parameters are as below:
1. $info array with two keys 'all' and 'running'.Key 'running' contains response header(curl_getinfo()) for each running task.Key 'all' contains global information with keys as below:
    + $info['all']['downloadSpeed'] Download speed.
    + $info['all']['bodySize'] Body sized downloaded.
    + $info['all']['headerSize'] Header size downloaded.
    + $info['all']['activeNum'] Task has IO activity.
    + $info['all']['queueNum'] Tasks waiting for onSuccess.
    + $info['all']['finishNum'] Tasks has finished.
    + $info['all']['cacheNum'] Cache hits.
    + $info['all']['failNum'] Failed tasks number after retry.
    + $info['all']['taskNum'] Task number in the task pool.
    + $info['all']['taskRunningNum'] Running task number.
    + $info['all']['taskPoolNum'] Task pool number.
    + $info['all']['taskFailNum'] Retrying task number.
2. Current Curl instance.
3. Is last call or not.

```PHP
public $onEvent = null
```
Triggered on IO events.The callback parameter is current Curl instance.

```PHP
public $onFail = null
```
Global callback for failed task which can be overwritten by task 'onTask'.The callback receive two parameters.
1. array with keys as below：
  + errorCode CURLE_* constants.
  + errorMsg Error message.
  + info Response header.
  + curl Current Curl instance.
2. $item['args'] value from Curl::add().

```PHP
public function add(array $item, $onSuccess = null, $onFail = null, $ahead = null)
```
Add one task to the pool.
+ $item
    1. $item['opt']=array() CURLOPT_\* for current task.
    2. $item['args'] Parameters for callbacks.
    3. $item['cache']=array() Cache config for current task.
+ $onSuccess Triggered on task finish.
    + Callback has two Parameters：
        1. $result Array with keys as below:
            + $result['info'] Response header.
            + $result['curl'] Current Curl instance.
            + $result['body'] Response body.Not exist in download task.
            + $result['header'] Raw response header.Exists when CURLOPT_HEADER was enabled.
            + $result['cacheFile'] Exists when cache is used.
        2. Value from $item['args']
    + Values can be returned.Must be array if exist.Array keys is as below:
        + cache Same format with Curl::$cache.This is the last chance to control caching.
+ $onFail Overwrite Curl::$onFail。
+ $ahead Add to high priority poll or not.

Return: current Curl instance.

```PHP
public function start()
```
Start the event loop and block.

```PHP
public function stop()
```
Stop the event loop and return unprocessed tasks.

```PHP
public function parseResponse($response)
```
Parse http header and body from response.

```PHP
public function getCacheFile($url, $post = null)
```
Generate relative cache path.

## Toolkit (src/Toolkit.php Necessary tools) 
```PHP
function setCurl($curl = null)
```
Default Curl instance is used if $curl is null.

The default instance will initialize Curl::$opt,Curl::onInfo,Curl::onFail. Curl::$opt initial values are as follows:
```PHP
array(
    CURLINFO_HEADER_OUT => true,
    CURLOPT_HEADER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_AUTOREFERER => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_MAXREDIRS => 5
)
```

```PHP
function onFail($error, $args)
```
Default fail callback.See Curl::$onFail for details.

```PHP
function onInfo($info)
```
Default info callback.See Curl::onInfo for details.

The method can be triggered manually with a string parameter which will be added to output buffer.The purpose is to avoid interference of shell control characters.

```PHP
function htmlEncode($html, $in = null, $out = 'UTF-8', $mode = 'auto')
```
Powerful html encoding transformer which can get current encoding automatically and replace html encoding value in \<head\>\</head\>.
Parameters：
+ $html Html string.
+ $in Current encoding.It's best to specify one.
+ $out Target encoding.
+ $mode auto|iconv|mb_convert_encoding.

Return:
New encoded html.

```PHP
function isUrl($url)
```
Full url or not.Return bool.

```PHP
function formatUrl($url)
```
Replace space,process scheme and hosts and remove anchor etc.

```PHP
function buildUrl(array $parse)
```
Inverse function for parse_url().

```PHP
function uri2url($uri, $urlCurrent)
```
Transform uri to full url for currentPage.$urlCurrent should be redirected after 3xx.

```PHP
function url2uri($url, $urlCurrent)
```
Transform full url to uri for currentPage.$urlCurrent should be redirected after 3xx.

```PHP
function url2dir($url)
```
Transform full url to dir.$urlCurrent should be redirected after 3xx.

```PHP
function url2absolute($url, $urlCurrent)
```
Combine a base URL and a relative URL to produce a new absolute URL.

```PHP
function urlRemoveDotSegments($path)
```
Filter out "." and ".." segments from a URL's path and return the result.

```PHP
function getCurl()
```
Return current Curl instance.