<?php
namespace Ares333\Curlmulti;

class Curl
{

    // max parallel num
    public $maxThread = 10;

    // retry time(s) when curl error happend
    public $maxTry = 3;

    // class level curl opt
    public $opt = array(
        CURLINFO_HEADER_OUT => true,
        CURLOPT_HEADER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_AUTOREFERER => true,
        // more useragent http://www.useragentstring.com/
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5
    );

    public $cache = array(
        'enable' => false,
        'compress' => false,
        'dir' => null,
        'expire' => 86400,
        'verifyPost' => false
    );

    // stack or queue
    public $taskPoolType = 'queue';

    // task callback
    public $onTask;

    // status callback
    public $onInfo;

    // user callback
    public $onUser;

    // common fail callback, called if no one specified
    public $onFail;

    // is the loop running
    protected $isRunning = false;

    // all tasks are saved here first
    protected $taskPool = array();

    // taskPool with high priority
    protected $taskPoolAhead = array();

    // running task(s)
    protected $taskRunning = array();

    // failed task(s) needed to retry
    protected $taskFail = array();

    // main handler
    private $mh;

    // running info
    private $info = array(
        'all' => array(
            // the real running parallel num
            'activeNum' => 0,
            // finished task needed to process
            'queueNum' => 0,
            // network speed, byte
            'downloadSpeed' => 0,
            // body size, byte
            'bodySize' => 0,
            // header size, byte
            'headerSize' => 0,
            // all finished task number,include failed task and cache
            'finishNum' => 0,
            // The number of cache used
            'cacheNum' => 0,
            // completely failed task number
            'failNum' => 0,
            // task num has been added to the poll
            'taskNum' => 0,
            // running task(s) count
            'taskRunningNum' => 0,
            // task pool count
            'taskPoolNum' => 0,
            // task(s) failed and waiting to retry count
            'taskFailNum' => 0
        ),
        'running' => array()
    );

    // used for calculate download speed
    private $sizeDownloaded = 0;

    /**
     * Add a task to the taskPool
     *
     * @param array $item
     *            array('opt'=>array(),['args'=>null],['cache'=>array()])
     * @param mixed $onProcess
     *            first arg: array('info'=>array(),'body'=>'','header'=>array(),['cache'=>array('file'=>'')])
     *            second arg: $item[args]
     *            [return array('cache'=>array())]
     * @param mixed $onFail
     *            first arg: array('error'=>array(0=>code,1=>msg),'info'=>array())
     *            second arg: $item[args];
     * @param bool $ahead
     *            higher priority
     * @return self
     */
    function add(array $item, $onProcess = null, $onFail = null, $ahead = null)
    {
        if (! isset($ahead)) {
            $ahead = false;
        }
        if (! isset($item['opt'])) {
            $item['opt'] = array();
        }
        if (! isset($item['args'])) {
            $item['args'] = array();
        }
        if (! isset($item['cache'])) {
            $item['cache'] = array();
        }
        if (! isset($item['opt'][CURLOPT_URL])) {
            $item['opt'][CURLOPT_URL] = '';
        }
        $item['opt'][CURLOPT_URL] = trim($item['opt'][CURLOPT_URL]);
        // replace space with + to avoid some curl problems
        $item['opt'][CURLOPT_URL] = str_replace(' ', '+',
            $item['opt'][CURLOPT_URL]);
        $parse = parse_url($item['opt'][CURLOPT_URL]);
        $keys = array(
            'scheme',
            'host',
            'port',
            'user',
            'pass',
            'path',
            'query',
            'fragment'
        );
        foreach ($keys as $v) {
            if (! isset($parse[$v])) {
                $parse[$v] = '';
            }
        }
        if ('' !== $parse['user']) {
            $parse['user'] .= ':';
            $parse['pass'] .= '@';
        }
        if ('' !== $parse['port']) {
            $parse['host'] .= ':';
        }
        if ('' !== $parse['query']) {
            $parse['path'] .= '?';
        }
        strtolower($parse['scheme']);
        strtolower($parse['host']);
        $item['opt'][CURLOPT_URL] = $parse['scheme'] . '://' . $parse['user'] .
             $parse['pass'] . $parse['host'] . $parse['port'] . $parse['path'] .
             $parse['query'];
        $task = array();
        $task['args'] = array(
            $item['args']
        );
        $task['opt'] = $item['opt'];
        $task['cache'] = $item['cache'];
        $task['process'] = $onProcess;
        $task['fail'] = $onFail;
        $task['tried'] = 0;
        $task['ch'] = null;
        // add
        if (true == $ahead) {
            $this->taskPoolAhead[] = $task;
        } else {
            $this->taskPool[] = $task;
        }
        $this->info['all']['taskNum'] ++;
        return $this;
    }

    /**
     * start loop
     */
    function start()
    {
        if ($this->isRunning) {
            user_error(__CLASS__ . ' is running !', E_USER_ERROR);
        }
        $this->mh = curl_multi_init();
        $this->isRunning = true;
        $this->addTask();
        do {
            $this->exec();
            curl_multi_select($this->mh);
            $this->onInfo();
            if (isset($this->onUser)) {
                call_user_func($this->onUser);
            }
            while (false != ($curlInfo = curl_multi_info_read($this->mh,
                $this->info['all']['queueNum']))) {
                $ch = $curlInfo['handle'];
                $task = $this->taskRunning[(int) $ch];
                $info = curl_getinfo($ch);
                $this->info['all']['bodySize'] += $info['size_download'];
                $this->info['all']['headerSize'] += $info['header_size'];
                if ($curlInfo['result'] == CURLE_OK) {
                    $param = array();
                    $param['info'] = $info;
                    if (! isset($task['opt'][CURLOPT_FILE])) {
                        $param['body'] = curl_multi_getcontent($ch);
                        if (isset($task['opt'][CURLOPT_HEADER])) {
                            preg_match_all("/HTTP\/.+(?=\r\n\r\n)/Usm",
                                $param['body'], $param['header']);
                            $param['header'] = $param['header'][0];
                            $pos = 0;
                            foreach ($param['header'] as $v) {
                                $pos += strlen($v) + 4;
                            }
                            $param['body'] = substr($param['body'], $pos);
                        }
                    }
                }
                curl_multi_remove_handle($this->mh, $ch);
                $curlError = curl_error($ch);
                curl_close($ch);
                if ($curlInfo['result'] == CURLE_OK) {
                    $this->onProcess($task, $param);
                }
                // error handle
                $callFail = false;
                if ($curlInfo['result'] !== CURLE_OK) {
                    if ($task['tried'] >= $this->maxTry) {
                        $err = array(
                            'error' => array(
                                $curlInfo['result'],
                                $curlError
                            )
                        );
                        $err['info'] = $info;
                        if (isset($task['fail']) || isset($this->onFail)) {
                            array_unshift($task['args'], $err);
                            $callFail = true;
                        } else {
                            user_error(
                                "Error " . implode(', ', $err['error']) .
                                     ", url=$info[url]", E_USER_WARNING);
                        }
                        $this->info['all']['failNum'] ++;
                    } else {
                        $task['tried'] ++;
                        $task['cache']['enable'] = false;
                        $this->taskFail[] = $task;
                        $this->info['all']['taskNum'] ++;
                    }
                }
                if ($callFail) {
                    if (isset($task['fail'])) {
                        call_user_func_array($task['fail'], $task['args']);
                    } elseif (isset($this->onFail)) {
                        call_user_func_array($this->onFail, $task['args']);
                    }
                }
                unset($this->taskRunning[(int) $ch]);
                $this->info['all']['finishNum'] ++;
                $this->sizeDownloaded += $info['size_download'] +
                     $info['header_size'];
                $this->addTask();
                $this->exec();
                $this->onInfo();
                if (isset($this->onUser)) {
                    call_user_func($this->onUser);
                }
            }
        } while ($this->info['all']['activeNum'] ||
             $this->info['all']['queueNum'] || ! empty($this->taskFail) ||
             ! empty($this->taskRunning) || ! empty($this->taskPool));
        $this->onInfo(true);
        curl_multi_close($this->mh);
        unset($this->mh);
        $this->isRunning = false;
    }

    /**
     * Call $this->onInfo
     *
     * @param bool $isLast
     *            is last output
     */
    private function onInfo($isLast = false)
    {
        static $downloadStartTime;
        static $downloadSpeed = array();
        static $lastTime = 0;
        $downloadSpeedRefreshTime = 3;
        if (! isset($downloadStartTime)) {
            $downloadStartTime = time();
        }
        $now = time();
        if (($isLast || $now - $lastTime > 0) && isset($this->onInfo)) {
            $this->info['all']['taskPoolNum'] = count($this->taskPool);
            $this->info['all']['taskRunningNum'] = count($this->taskRunning);
            $this->info['all']['taskFailNum'] = count($this->taskFail);
            // running
            $this->info['running'] = array();
            foreach ($this->taskRunning as $k => $v) {
                $this->info['running'][$k] = curl_getinfo($v['ch']);
            }
            if ($now - $downloadStartTime > 0) {
                if (count($downloadSpeed) > $downloadSpeedRefreshTime) {
                    array_shift($downloadSpeed);
                }
                $downloadSpeed[] = round(
                    $this->sizeDownloaded / ($now - $downloadStartTime));
                $this->info['all']['downloadSpeed'] = round(
                    array_sum($downloadSpeed) / count($downloadSpeed));
            }
            if ($now - $downloadStartTime > $downloadSpeedRefreshTime) {
                $this->sizeDownloaded = 0;
                $downloadStartTime = $now;
            }
            call_user_func_array($this->onInfo,
                array(
                    $this->info
                ));
            $lastTime = $now;
        }
    }

    /**
     * curl_multi_exec()
     */
    private function exec()
    {
        while (curl_multi_exec($this->mh, $this->info['all']['activeNum']) ===
             CURLM_CALL_MULTI_PERFORM) {
                continue;
        }
    }

    /**
     * Add a task to curl, keep $this->maxThread num automatically
     */
    private function addTask()
    {
        $c = $this->maxThread - count($this->taskRunning);
        while ($c > 0) {
            $task = array();
            // search failed first
            if (! empty($this->taskFail)) {
                $task = array_pop($this->taskFail);
            } else {
                // onTask
                if (empty($this->taskPool) && empty($this->taskPoolAhead) &&
                     isset($this->onTask)) {
                    call_user_func($this->onTask);
                }
                if (! empty($this->taskPoolAhead)) {
                    $task = array_pop($this->taskPoolAhead);
                } elseif (! empty($this->taskPool)) {
                    if ($this->taskPoolType == 'stack') {
                        $task = array_pop($this->taskPool);
                    } else {
                        $task = array_shift($this->taskPool);
                    }
                }
            }
            $cache = null;
            if (! empty($task)) {
                $cache = $this->cache($task);
                if (null !== $cache) {
                    // download task
                    if (isset($task['opt'][CURLOPT_FILE])) {
                        if (flock($task['opt'][CURLOPT_FILE], LOCK_EX)) {
                            fwrite($task['opt'][CURLOPT_FILE], $cache['body']);
                            flock($task['opt'][CURLOPT_FILE], LOCK_UN);
                        }
                        unset($cache['body']);
                    }
                    $this->onProcess($task, $cache);
                    $this->info['all']['cacheNum'] ++;
                    $this->info['all']['finishNum'] ++;
                    $this->onInfo();
                } else {
                    $task = $this->initTask($task);
                    $this->taskRunning[(int) $task['ch']] = $task;
                    curl_multi_add_handle($this->mh, $task['ch']);
                }
            } else {
                break;
            }
            if (null == $cache) {
                $c --;
            }
        }
    }

    /**
     * Process response
     *
     * @param array $task
     * @param array $param
     */
    private function onProcess($task, $param)
    {
        array_unshift($task['args'], $param);
        $userRes = array();
        if (isset($task['process'])) {
            $userRes = call_user_func_array($task['process'], $task['args']);
        }
        if (isset($userRes['cache'])) {
            $task['cache'] = array_merge($task['cache'], $userRes['cache']);
        }
        // write cache
        if (! isset($param['cache'])) {
            $this->cache($task, $param);
        }
    }

    /**
     * Set or get file cache
     *
     * @param string $url
     * @param array|null $data
     * @return mixed
     */
    private function cache($task, $data = null)
    {
        $config = array_merge($this->cache, $task['cache']);
        if (! $config['enable']) {
            return;
        }
        if (! isset($config['dir']))
            user_error('cache dir is not defined', E_USER_ERROR);
        $url = $task['opt'][CURLOPT_URL];
        // verify post
        $suffix = '';
        if (true == $config['verifyPost'] &&
             ! empty($task['opt'][CURLOPT_POSTFIELDS])) {
            $post = $task['opt'][CURLOPT_POSTFIELDS];
            if (is_array($post)) {
                $post = http_build_query($post);
            }
            $suffix .= $post;
        }
        $key = md5($url . $suffix);
        // calculate file
        $file = rtrim($config['dir'], '/') . '/';
        $file .= substr($key, 0, 3) . '/' . substr($key, 3, 3) . '/' .
             substr($key, 6);
        if (! isset($data)) {
            if (file_exists($file)) {
                $time = time();
                $mtime = filemtime($file);
                if ($time - $mtime < $config['expire']) {
                    $r = file_get_contents($file);
                    if ($config['compress']) {
                        $r = gzuncompress($r);
                    }
                    $r = unserialize($r);
                    return $r;
                }
            }
        } else {
            if (! isset($data['cache'])) {
                $data['cache'] = array();
            }
            $data['cache']['file'] = $file;
            $dir = dirname($file);
            $dir1 = dirname($dir);
            if (! is_dir($dir1)) {
                mkdir($dir1);
            }
            if (! is_dir($dir)) {
                mkdir($dir);
            }
            if (isset($task['opt'][CURLOPT_FILE])) {
                $data['body'] = file_get_contents(
                    stream_get_meta_data($task['opt'][CURLOPT_FILE])['uri']);
            }
            $data = serialize($data);
            if ($config['compress']) {
                $data = gzcompress($data);
            }
            file_put_contents($file, $data, LOCK_EX);
        }
    }

    /**
     *
     * @param array $task
     * @return array
     */
    private function initTask($task)
    {
        $task['ch'] = curl_init();
        $opt = $this->opt;
        foreach ($task['opt'] as $k => $v) {
            $opt[$k] = $v;
        }
        curl_setopt_array($task['ch'], $opt);
        $task['opt'] = $opt;
        return $task;
    }
}
