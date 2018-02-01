## [中文文档](README_CN.md "中文文档")

## About

Implemented by using curlmulti internal io event.It's a high performance,high universality,high expansibility library which especially suitable for massive scale tasks and complex logic case.

## Demand
PHP: >=5.3

## Install
```
composer require ares333/php-curl
```

## Features
1. Extremely low cpu and memory usage and very high performance(More than 3000 html requests finished in some cases and download speed can reach 1000Mbps in servers with a 1Gbps network interface).
2. All curl options exposed directly,so high universality and high expansibility is possible.At the same time has high usability(Only has 2 public methods).
3. Support process disruption and resume from last running state.
4. Support dynamic tasks.
5. Support transparent file cache.
6. Support retry faild tasks automaticlly.
7. Support global config,task config,callback config with same config format with priority from high to low.
8. All configs can be changed on the fly and take effact immediately.

## Work Flow
Curl::add() add tasks to task pool.Curl::start() start the event loop and blocked.Events(onSuccess,onFail,onInfo,onTask...) will be triggered and corresponding callbacks will be called on the fly.The loop finished when all tasks in the task poll finished.

## Tutorial
**basic**
```PHP
$curl = new Curl();
$curl->add(
    array(
        'opt' => array(
            CURLOPT_URL => 'http://baidu.com'
        ),
        'args' => 'This is user arg for ' . $v
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
$curl = new Curl();
$url = 'http://www.baidu.com/img/bd_logo1.png';
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
        echo "download finished successfully, file=$args[file]\n";
    })->start();
```
**task callback**

Task can be added in task callback. See more details in Curl::$onTask.
```PHP
$curl = (new Toolkit())->getCurl();
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
```
**running info**
```PHP
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
run in cli and will out info with format:
```
SPD    DWN  FNH  CACHE  RUN  ACTIVE  POOL  QUEUE  TASK  FAIL  
457KB  3MB  24   0      3    3       73    0      100   0
```
Info callback will receive all information.The default callback only show part of it.
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
$curl = new Curl();
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
```
Output is as below:
```
add http://baidu.com
finish https://www.baidu.com/
add http://bing.com
finish http://cn.bing.com/
```
Finished url has a / at end because curl processed the 3xx redirect(Curl::$opt[CURLOPT_FOLLOWLOCATION]=true).
Curl::onTask should be used when deal with massive sale tasks.

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
Global CURLOPT_\* which can be overwrite by task config.

```PHP
public $cache = array(
    'enable' => false,
    'compress' => 0, //0-9,6 is a good choice if you want use compress.
    'dir' => null, //Cache dir which must exists.
    'expire' => 86400,
    'verifyPost' => false //If http post will be part of cache id.
);
```
Global cache config.Cache id is related with url.This config can be overwrite by task config and onSuccess callback return value with same config format.

```PHP
public $taskPoolType = 'queue'
```
stack or queue.

```PHP
public $onTask = null
```
Will be triggered when parallel count is less than Curl::$maxThread and task pool is empty.Only argument for callbak is current Curl instance.

```PHP
public $onInfo = null
```
Running state callback which triggered when IO event happens.Triggered one second at most.Callback arguments are as below:
1. $info array with two keys 'all' and 'running'.Key 'running' contains response header(curl_getinfo()) for each running task.Key 'all' contains global information with keys as below:
    + $info['all']['downloadSpeed'] Download speed.
    + $info['all']['bodySize'] Body sized downloaded.
    + $info['all']['headerSize'] Header size downloaded.
    + $info['all']['activeNum'] Task has IO activity.
    + $info['all']['queueNum'] Tasks waiting for onSuccess.
    + $info['all']['finishNum'] Tasks has been processed.
    + $info['all']['cacheNum'] Cache using count.
    + $info['all']['failNum'] Failed task count after retry.
    + $info['all']['taskNum'] Task count added to the pool.
    + $info['all']['taskRunningNum'] Running task count.
    + $info['all']['taskPoolNum'] Task pool count.
    + $info['all']['taskFailNum'] Task count which are retrying.
2. Current Curl instance.
3. bool, is last call or not.

```PHP
public $onEvent = null
```
Triggered on IO event.Only argument for callbak is current Curl instance.

```PHP
public $onFail = null
```
Global callback for fail.Can be overwrite by task onTask.The callback receive two arguments.
1. array with key as below：
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
    1. $item['opt']=array() CURLOPT_\* constants for current task.
    2. $item['args'] Arguments for callbacks.
    3. $item['cache']=array() Cache config for current task.
+ $onSuccess Triggered on task finish.
    + Callback has two arguments：
        1. $result array with keys as below:
            + $result['info'] Response header.
            + $result['curl'] Current Curl instance.
            + $result['body'] Response body.Not exist in download task.
            + $result['header'] Raw response header.Exists when CURLOPT_HEADER was enabled.
            + $result['cacheFile'] Exists when cache is used.
        2. Value from $item['args']
    + Return value can be setted.Must be array if setted.Array keys is as below:
        + cache Same format with Curl::$cache.This is the last chance to set.
+ $onFail overwrite Curl::$onFail。
+ $ahead Add to high priority poll or not.

Return: current Curl instance.

```PHP
public function start()
```
Start the event loop and block.

```PHP
public function stop($onStop = null)
```
Stop the event loop and $onStop will be called when the loop has been stoped.
Only argument for callbak is current Curl instance.

## Toolkit (src/Toolkit.php Necessary tools) 
```PHP
function __construct(Curl $curl = null)
```
Default Curl instance is used if $curl is not setted.

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
Default onFail.See Curl::$onFail for details.

```PHP
function onInfo($info)
```
Default onInfo.See Curl::onInfo for details.

The method can be call manually with a string parameter which will be added to output buffer.The purpose is to avoid the effects of shell control characters.

```PHP
function htmlEncode($html, $in = null, $out = 'UTF-8', $mode = 'auto')
```
Powerful html encoding tranformer which can get current automatically and replace encoding in \<head\>\</head\>.
Arguments：
+ $html Html string.
+ $in Current encoding.It's best to specify one.
+ $out Target encoding.
+ $mode auto|iconv|mb_convert_encoding，auto will choose automatically.

Return:
New encoded html.

```PHP
function isUrl($url)
```
Full url or not.Return bool.

```PHP
function urlFormater($url)
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
function getCurl()
```
return current Curl instance.

## HttpClone (src/HttpClone.php Website clone) 
```PHP
public $expire = null
```
Local file expire time.

```PHP
public $download = array(
	'pic' => true,
	'video' => false
);
```
false will use remote file.

```PHP
public $blacklist = array();
```
Used for urls out of work.

```PHP
public $downloadExtension = array();
```
Download by extension(in html tag a),for example zip,rar.

```PHP
public $httpCode = array(
    200
);
```
Valid http code.Invalid http code will be reported and ignored.

```PHP
function __construct($dir)
```
Local top directory.

```PHP
function add($url, $depth = null)
```
Add a start url.All sub urls with prefix $url will be download if $depth is null.
Return: Self instance.

```PHP
function start()
```
Start clone and block.

## Http website clone
Based on Curl and Toolkit,inherit power of Curl and has self features as below:
1. Same page will be processed only once.3xx and malformed url will be dealed automatically.
2. Url and uri including remote and local will be processed automatically.
3. All local link will be a file(not directory),with this all files can be placed on cdn or aws s3 or something like.
4. Resource in style and css tag will be processed automatically,@import expression is supported recursively.
5. Support download by file extension.Form action process automatically.
6. Support multi start url with depth.
7. Original site structure is  reserved.Data integrity is guaranted from underlying.

Notice: Clone is a very complex work and was tested with limited website.Below is the demo from some of the tests:

demo1: [Source](http://www.laruence.com/manual/)  [Clone](http://demo-curl.phpdr.net/clone/http_www.laruence.com/manual/index.html)

demo2: Source has been closed  [Clone](http://demo-curl.phpdr.net/clone/http_yamlcss.meezhou.com/index.html)

demo3: [Source](http://www.handubaby.com/)  [Clone](http://demo-curl.phpdr.net/clone/http_www.handubaby.com/index.html)

demo4: [Source](http://www.bjszxx.cn/) [Clone](http://demo-curl.phpdr.net/clone/http_www.bjszxx.cn/index.html)
