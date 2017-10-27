<?php
namespace Ares333\Curl;

/**
 * The best curlmulti library.
 *
 * @author Ares
 */
class Curl
{

    public $maxThread = 10;

    // Max try times on curl error
    public $maxTry = 0;

    // Global CURLOPT_*
    public $opt = array(
        CURLINFO_HEADER_OUT => true,
        CURLOPT_HEADER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_AUTOREFERER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5
    );

    // Global config.
    public $cache = array(
        'enable' => false,
        /**
         * The level of compression.
         * Can be given as 0 for no compression up to 9 for maximum compression.
         * 6 is a good choice.
         */
        'compress' => 0,
        'dir' => null,
        'expire' => 86400,
        // Different post data different cache file when enabled.
        'verifyPost' => false
    );

    // stack or queue
    public $taskPoolType = 'queue';

    // Emmited when new tasks are needed.
    public $onTask;

    // Emmited on IO event.At least 1 second interval.
    public $onInfo;

    // Emitted on curl error.
    public $onFail;

    // Emitted on IO event.
    public $onEvent;

    protected $mh;

    protected $taskPool = array();

    // Task pool with higher priority.
    protected $taskPoolAhead = array();

    protected $taskRunning = array();

    // Failed tasks retrying.
    protected $taskFailed = array();

    protected $stoping = false;

    // Handle serialization callback temporarily
    protected $onStop = null;

    protected static $running = false;

    // Running info.
    protected $info = array(
        'all' => array(
            'downloadSpeed' => 0,
            'bodySize' => 0,
            'headerSize' => 0,
            // Active requests count
            'activeNum' => 0,
            // Finished requests count on the queue
            'queueNum' => 0,
            // Finished tasks count including failed tasks and tasks using cache.
            'finishNum' => 0,
            // Cache used count
            'cacheNum' => 0,
            // Count of tasks failed after retry
            'failNum' => 0,
            // Count of tasks added to the poll
            'taskNum' => 0,
            // $this->taskRunning count.taskRunning >= active + queue
            'taskRunningNum' => 0,
            // $this->taskPool count
            'taskPoolNum' => 0,
            // $this->taskFail count
            'taskFailNum' => 0
        ),
        'running' => array()
    );

    protected $onInfoLastTime = 0;

    protected $downloadSpeedStartTime;

    protected $downloadSpeedTotalSize = 0;

    protected $downloadSpeedList = array();

    /**
     * Add a task to the taskPool
     *
     * @param array $item
     *            array $item[opt] CURLOPT_* for current task
     *            mixed $item[args] Args for callbacks
     *            array $item[cache]
     * @param mixed $onSuccess
     *            Callback for response
     * @param mixed $onFail
     *            Callback for curl error
     * @param bool $ahead
     * @return self
     */
    public function add(array $item, $onSuccess = null, $onFail = null, $ahead = null)
    {
        if (! isset($ahead)) {
            $ahead = false;
        }
        if (! isset($item['opt'])) {
            $item['opt'] = array();
        }
        if (! isset($item['args'])) {
            $item['args'] = null;
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
        $parse['path'] = preg_replace('/\/+/', '/', $parse['path']);
        strtolower($parse['scheme']);
        strtolower($parse['host']);
        $item['opt'][CURLOPT_URL] = $parse['scheme'] . '://' . $parse['user'] .
             $parse['pass'] . $parse['host'] . $parse['port'] . $parse['path'] .
             $parse['query'];
        $task = array();
        $task['args'] = $item['args'];
        $task['opt'] = $item['opt'];
        $task['cache'] = $item['cache'];
        $task['onSuccess'] = $onSuccess;
        $task['onFail'] = $onFail;
        $task['tried'] = 0;
        $task['ch'] = null;
        // $task['fileMeta'] is used for download cache and __wakeup
        if (isset($task['opt'][CURLOPT_FILE])) {
            $task['fileMeta'] = stream_get_meta_data($task['opt'][CURLOPT_FILE]);
        } else {
            $task['fileMeta'] = array();
        }
        // add
        if (true == $ahead) {
            $this->taskPoolAhead[] = $task;
        } else {
            $this->taskPool[] = $task;
        }
        $this->info['all']['taskNum'] ++;
        return $this;
    }

    public function __wakeup()
    {
        foreach (array(
            &$this->taskFailed,
            &$this->taskPoolAhead,
            &$this->taskPool
        ) as &$v) {
            foreach ($v as &$v1) {
                if (isset($v1['fileMeta']['uri'])) {
                    $v1['opt'][CURLOPT_FILE] = fopen($v1['fileMeta']['uri'],
                        $v1['fileMeta']['mode']);
                    $v1['fileMeta'] = stream_get_meta_data(
                        $v1['opt'][CURLOPT_FILE]);
                }
            }
        }
        unset($v, $v1);
    }

    /**
     *
     * @param callable $onStop
     */
    public function stop($onStop = null)
    {
        if (! self::$running || $this->stoping) {
            return;
        }
        $this->stoping = true;
        $this->onStop = $onStop;
    }

    protected function onStop()
    {
        $this->stoping = false;
        foreach (array(
            $this->taskFailed,
            $this->taskRunning,
            $this->taskPool,
            $this->taskPoolAhead
        ) as $v) {
            foreach ($v as $v1) {
                if (isset($v1['opt'][CURLOPT_FILE]) &&
                     is_resource($v1['opt'][CURLOPT_FILE])) {
                    fclose($v1['opt'][CURLOPT_FILE]);
                }
                if (isset($v1['fileMeta']['uri']) && is_file(
                    $v1['fileMeta']['uri'])) {
                    unlink($v1['fileMeta']['uri']);
                }
            }
        }
        foreach (array(
            &$this->taskRunning
        ) as $k => &$v) {
            foreach ($v as $k1 => $v1) {
                if (is_resource($v1['ch'])) {
                    curl_multi_remove_handle($this->mh, $v1['ch']);
                    curl_close($v1['ch']);
                }
                unset($v[$k1]);
                array_unshift($this->taskPoolAhead, $v1);
            }
        }
        unset($v);
        $this->downloadSpeedStartTime = null;
        $this->onInfoLastTime = 0;
        $this->downloadSpeedTotalSize = 0;
        $this->downloadSpeedList = array();
        $this->info['all']['downloadSpeed'] = 0;
        $this->info['all']['activeNum'] = 0;
        $this->info['all']['queueNum'] = 0;
        if (isset($this->onStop)) {
            // Serialization of 'Closure' is not allowed
            $onStop = $this->onStop;
            unset($this->onStop);
            call_user_func($onStop, $this);
        }
    }

    public function start()
    {
        if (self::$running) {
            user_error(__CLASS__ . ' is running !', E_USER_ERROR);
        }
        self::$running = true;
        $this->mh = curl_multi_init();
        $this->runTask();
        do {
            $this->exec();
            $this->onInfo();
            if ($this->stoping) {
                $this->onStop();
                break;
            }
            curl_multi_select($this->mh);
            if (isset($this->onEvent)) {
                call_user_func($this->onEvent, $this);
            }
            while (false != ($curlInfo = curl_multi_info_read($this->mh,
                $this->info['all']['queueNum']))) {
                if ($this->stoping) {
                    $this->onStop();
                    break 2;
                }
                $ch = $curlInfo['handle'];
                $task = $this->taskRunning[(int) $ch];
                $info = curl_getinfo($ch);
                $this->info['all']['bodySize'] += $info['size_download'];
                $this->info['all']['headerSize'] += $info['header_size'];
                $param = array();
                $param['info'] = $info;
                $param['curl'] = $this;
                if ($curlInfo['result'] == CURLE_OK) {
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
                $curlError = curl_error($ch);
                curl_multi_remove_handle($this->mh, $ch);
                curl_close($ch);
                if (isset($task['opt'][CURLOPT_FILE])) {
                    fclose($task['opt'][CURLOPT_FILE]);
                }
                if ($curlInfo['result'] == CURLE_OK) {
                    $this->onProcess($task, $param);
                }
                // Handle error
                if ($curlInfo['result'] !== CURLE_OK) {
                    if ($task['tried'] >= $this->maxTry) {
                        $param['errorCode'] = $curlInfo['result'];
                        $param['errorMsg'] = $curlError;
                        if (isset($task['onFail'])) {
                            call_user_func($task['onFail'], $param,
                                $task['args']);
                        } elseif (isset($this->onFail)) {
                            call_user_func($this->onFail, $param, $task['args']);
                        } else {
                            user_error(
                                "Curl error($curlInfo[result]) $info[url]",
                                E_USER_WARNING);
                        }
                        $this->info['all']['failNum'] ++;
                    } else {
                        $task['tried'] ++;
                        $this->taskFailed[] = $task;
                        $this->info['all']['taskNum'] ++;
                    }
                }
                unset($this->taskRunning[(int) $ch]);
                $this->info['all']['finishNum'] ++;
                $this->downloadSpeedTotalSize += $info['size_download'] +
                     $info['header_size'];
                $this->runTask();
                $this->exec();
                $this->onInfo();
                if (isset($this->onEvent)) {
                    call_user_func($this->onEvent, $this);
                }
            }
        } while ($this->info['all']['activeNum'] ||
             $this->info['all']['queueNum'] || ! empty($this->taskFailed) ||
             ! empty($this->taskRunning) || ! empty($this->taskPool));
        $this->onInfo(true);
        curl_multi_close($this->mh);
        $this->mh = null;
        self::$running = false;
    }

    /**
     * Call $this->onInfo
     *
     * @param bool $isLast
     *            Is last output?
     */
    protected function onInfo($isLast = false)
    {
        $now = time();
        if (! isset($this->downloadSpeedStartTime)) {
            $this->downloadSpeedStartTime = $now;
        }
        if (($isLast || $now - $this->onInfoLastTime > 0) && isset(
            $this->onInfo)) {
            $this->info['all']['taskPoolNum'] = count($this->taskPool);
            $this->info['all']['taskPoolNum'] += count($this->taskPoolAhead);
            $this->info['all']['taskRunningNum'] = count($this->taskRunning);
            $this->info['all']['taskFailNum'] = count($this->taskFailed);
            // running
            $this->info['running'] = array();
            foreach ($this->taskRunning as $k => $v) {
                $this->info['running'][$k] = curl_getinfo($v['ch']);
            }
            if ($now - $this->downloadSpeedStartTime > 0) {
                if (count($this->downloadSpeedList) > 10) {
                    array_shift($this->downloadSpeedList);
                }
                $this->downloadSpeedList[] = round(
                    $this->downloadSpeedTotalSize /
                         ($now - $this->downloadSpeedStartTime));
                $this->info['all']['downloadSpeed'] = round(
                    array_sum($this->downloadSpeedList) /
                         count($this->downloadSpeedList));
            }
            if ($now - $this->downloadSpeedStartTime > 3) {
                $this->downloadSpeedTotalSize = 0;
                $this->downloadSpeedStartTime = $now;
            }
            call_user_func($this->onInfo, $this->info, $this);
            $this->onInfoLastTime = $now;
        }
    }

    protected function exec()
    {
        while (curl_multi_exec($this->mh, $this->info['all']['activeNum']) ===
             CURLM_CALL_MULTI_PERFORM) {
                continue;
        }
    }

    protected function runTask()
    {
        $c = $this->maxThread - count($this->taskRunning);
        while ($c > 0) {
            $task = null;
            // search failed first
            if (! empty($this->taskFailed)) {
                $task = array_pop($this->taskFailed);
            } else {
                // onTask
                if (empty($this->taskPool) && empty($this->taskPoolAhead) &&
                     isset($this->onTask)) {
                    call_user_func($this->onTask, $this);
                }
                if (! empty($this->taskPoolAhead)) {
                    $task = array_shift($this->taskPoolAhead);
                } elseif (! empty($this->taskPool)) {
                    if ($this->taskPoolType == 'stack') {
                        $task = array_pop($this->taskPool);
                    } else {
                        $task = array_shift($this->taskPool);
                    }
                }
            }
            $cache = null;
            if (isset($task)) {
                $cache = $this->cache($task);
                if (null !== $cache) {
                    // download task
                    if (isset($task['opt'][CURLOPT_FILE])) {
                        if (flock($task['opt'][CURLOPT_FILE], LOCK_EX)) {
                            fwrite($task['opt'][CURLOPT_FILE], $cache['body']);
                            flock($task['opt'][CURLOPT_FILE], LOCK_UN);
                        }
                        fclose($task['opt'][CURLOPT_FILE]);
                        unset($cache['body']);
                    }
                    $cache['curl'] = $this;
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
    protected function onProcess($task, $param)
    {
        $userRes = array();
        if (isset($task['onSuccess'])) {
            $userRes = call_user_func($task['onSuccess'], $param, $task['args']);
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
     * Set or get file cache.
     *
     * @param string $url
     * @param array|null $data
     * @return mixed
     */
    protected function cache($task, $data = null)
    {
        $config = array_merge($this->cache, $task['cache']);
        if (! $config['enable']) {
            return;
        }
        if (! isset($config['dir'])) {
            user_error('cache dir is not defined', E_USER_WARNING);
            return;
        }
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
        $file = rtrim($config['dir'], '/') . '/';
        $file .= substr($key, 0, 3) . '/' . substr($key, 3, 3) . '/' .
             substr($key, 6);
        if (! isset($data)) {
            if (file_exists($file)) {
                $time = time();
                $mtime = filemtime($file);
                if ($time - $mtime < $config['expire']) {
                    return unserialize(gzuncompress(file_get_contents($file)));
                }
            }
        } else {
            if (! isset($data['cache'])) {
                $data['cache'] = array();
            }
            $data['cacheFile'] = $file;
            unset($data['curl']);
            $dir = dirname($file);
            $dir1 = dirname($dir);
            if (! is_dir($dir1)) {
                mkdir($dir1);
            }
            if (! is_dir($dir)) {
                mkdir($dir);
            }
            // Cache response from downloaded file.
            if (isset($task['fileMeta']['uri'])) {
                $data['body'] = file_get_contents($task['fileMeta']['uri']);
            }
            file_put_contents($file,
                gzcompress(serialize($data), $config['compress']), LOCK_EX);
        }
    }

    /**
     *
     * @param array $task
     * @return array
     */
    protected function initTask($task)
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
