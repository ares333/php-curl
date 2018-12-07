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
    public $opt = array();

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

    protected $_mh;

    protected $_taskPool = array();

    // Task pool with higher priority.
    protected $_taskPoolAhead = array();

    protected $_taskRunning = array();

    // Failed tasks retrying.
    protected $_taskFailed = array();

    protected $_stop = false;

    // Running info.
    protected $_info = array(
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

    protected $_onInfoLastTime = 0;

    protected $_downloadSpeedStartTime;

    protected $_downloadSpeedTotalSize = 0;

    protected $_downloadSpeedList = array();

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
        $item['opt'][CURLOPT_URL] = str_replace(' ', '+', $item['opt'][CURLOPT_URL]);
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
        $item['opt'][CURLOPT_URL] = $parse['scheme'] . '://' . $parse['user'] . $parse['pass'] . $parse['host'] .
            $parse['port'] . $parse['path'] . $parse['query'];
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
            $this->_taskPoolAhead[] = $task;
        } else {
            $this->_taskPool[] = $task;
        }
        $this->_info['all']['taskNum'] ++;
        return $this;
    }

    /**
     *
     * @return array[] tasks
     */
    public function stop()
    {
        $this->_stop = true;
        $tasks = [];
        foreach (array(
            '_taskPoolAhead',
            '_taskFailed',
            '_taskRunning',
            '_taskPool'
        ) as $v) {
            foreach ($this->$v as $k1 => $v1) {
                if (isset($v1['opt'][CURLOPT_FILE]) && is_resource($v1['opt'][CURLOPT_FILE])) {
                    fclose($v1['opt'][CURLOPT_FILE]);
                    if (is_file($v1['fileMeta']['uri'])) {
                        unlink($v1['fileMeta']['uri']);
                    }
                }
                if (is_resource($v1['ch'])) {
                    curl_multi_remove_handle($this->_mh, $v1['ch']);
                    curl_close($v1['ch']);
                }
                unset($this->{$v}[$k1]);
                $tasks[] = $v1;
            }
        }
        $this->_downloadSpeedStartTime = null;
        $this->_onInfoLastTime = 0;
        $this->_downloadSpeedTotalSize = 0;
        $this->_downloadSpeedList = array();
        $this->_info['all']['downloadSpeed'] = 0;
        $this->_info['all']['activeNum'] = 0;
        $this->_info['all']['queueNum'] = 0;
        return $tasks;
    }

    public function start()
    {
        $this->_stop = false;
        $this->_mh = curl_multi_init();
        $this->runTask();
        do {
            $this->exec();
            $this->onInfo();
            curl_multi_select($this->_mh);
            if (isset($this->onEvent)) {
                call_user_func($this->onEvent, $this);
            }
            if ($this->_stop) {
                break;
            }
            while (false != ($curlInfo = curl_multi_info_read($this->_mh, $this->_info['all']['queueNum']))) {
                $ch = $curlInfo['handle'];
                $task = $this->_taskRunning[(int) $ch];
                $info = curl_getinfo($ch);
                $this->_info['all']['bodySize'] += $info['size_download'];
                $this->_info['all']['headerSize'] += $info['header_size'];
                $param = array();
                $param['info'] = $info;
                $param['curl'] = $this;
                if ($curlInfo['result'] == CURLE_OK) {
                    if (! isset($task['opt'][CURLOPT_FILE])) {
                        $param['body'] = curl_multi_getcontent($ch);
                        if (isset($task['opt'][CURLOPT_HEADER])) {
                            $param = array_merge($param, $this->parseResponse($param['body']));
                        }
                    }
                }
                $curlError = curl_error($ch);
                curl_multi_remove_handle($this->_mh, $ch);
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
                            call_user_func($task['onFail'], $param, $task['args']);
                        } elseif (isset($this->onFail)) {
                            call_user_func($this->onFail, $param, $task['args']);
                        } else {
                            user_error("Curl error($curlInfo[result]) $info[url]", E_USER_WARNING);
                        }
                        $this->_info['all']['failNum'] ++;
                    } else {
                        $task['tried'] ++;
                        $this->_taskFailed[] = $task;
                        $this->_info['all']['taskNum'] ++;
                    }
                }
                unset($this->_taskRunning[(int) $ch]);
                $this->_info['all']['finishNum'] ++;
                $this->_downloadSpeedTotalSize += $info['size_download'] + $info['header_size'];
                $this->runTask();
                $this->exec();
                $this->onInfo();
                if (isset($this->onEvent)) {
                    call_user_func($this->onEvent, $this);
                }
                if ($this->_stop) {
                    break 2;
                }
            }
        } while ($this->_info['all']['activeNum'] || $this->_info['all']['queueNum'] || ! empty($this->_taskFailed) ||
            ! empty($this->_taskRunning) || ! empty($this->_taskPool));
        $this->onInfo(true);
        curl_multi_close($this->_mh);
        $this->_mh = null;
    }

    public function parseResponse($response)
    {
        $res = [];
        preg_match_all("/HTTP\/.+(?=\r\n\r\n)/Usm", $response, $res['header']);
        $res['header'] = $res['header'][0];
        $pos = 0;
        foreach ($res['header'] as $v) {
            $pos += strlen($v) + 4;
        }
        $res['body'] = substr($response, $pos);
        return $res;
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
        if (! isset($this->_downloadSpeedStartTime)) {
            $this->_downloadSpeedStartTime = $now;
        }
        if (($isLast || $now - $this->_onInfoLastTime > 0) && isset($this->onInfo)) {
            $this->_info['all']['taskPoolNum'] = count($this->_taskPool);
            $this->_info['all']['taskPoolNum'] += count($this->_taskPoolAhead);
            $this->_info['all']['taskRunningNum'] = count($this->_taskRunning);
            $this->_info['all']['taskFailNum'] = count($this->_taskFailed);
            // running
            $this->_info['running'] = array();
            foreach ($this->_taskRunning as $k => $v) {
                $this->_info['running'][$k] = curl_getinfo($v['ch']);
            }
            if ($now - $this->_downloadSpeedStartTime > 0) {
                if (count($this->_downloadSpeedList) > 10) {
                    array_shift($this->_downloadSpeedList);
                }
                $this->_downloadSpeedList[] = round(
                    $this->_downloadSpeedTotalSize / ($now - $this->_downloadSpeedStartTime));
                $this->_info['all']['downloadSpeed'] = round(
                    array_sum($this->_downloadSpeedList) / count($this->_downloadSpeedList));
            }
            if ($now - $this->_downloadSpeedStartTime > 3) {
                $this->_downloadSpeedTotalSize = 0;
                $this->_downloadSpeedStartTime = $now;
            }
            call_user_func($this->onInfo, $this->_info, $this, $isLast);
            $this->_onInfoLastTime = $now;
        }
    }

    protected function exec()
    {
        while (curl_multi_exec($this->_mh, $this->_info['all']['activeNum']) === CURLM_CALL_MULTI_PERFORM) {
            continue;
        }
    }

    protected function runTask()
    {
        $c = $this->maxThread - count($this->_taskRunning);
        while ($c > 0) {
            $task = null;
            // search failed first
            if (! empty($this->_taskFailed)) {
                $task = array_pop($this->_taskFailed);
            } else {
                // onTask
                if (empty($this->_taskPool) && empty($this->_taskPoolAhead) && isset($this->onTask)) {
                    call_user_func($this->onTask, $this);
                }
                if (! empty($this->_taskPoolAhead)) {
                    $task = array_shift($this->_taskPoolAhead);
                } elseif (! empty($this->_taskPool)) {
                    if ($this->taskPoolType == 'stack') {
                        $task = array_pop($this->_taskPool);
                    } else {
                        $task = array_shift($this->_taskPool);
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
                    $this->_info['all']['cacheNum'] ++;
                    $this->_info['all']['finishNum'] ++;
                    $this->onInfo();
                    if (isset($this->onEvent)) {
                        call_user_func($this->onEvent, $this);
                    }
                    if ($this->_stop) {
                        break;
                    }
                } else {
                    $task = $this->initTask($task);
                    $this->_taskRunning[(int) $task['ch']] = $task;
                    curl_multi_add_handle($this->_mh, $task['ch']);
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
     *
     * @param string $url
     * @param string|array $post
     * @return string
     */
    public function getCacheFile($url, $post = null)
    {
        $suffix = '';
        if (isset($post)) {
            if (is_array($post)) {
                $post = http_build_query($post);
                ksort($post);
            }
            $suffix .= $post;
        }
        $key = md5($url . $suffix);
        return substr($key, 0, 3) . '/' . substr($key, 3, 3) . '/' . substr($key, 6);
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
        $post = null;
        if (true == $config['verifyPost'] && ! empty($task['opt'][CURLOPT_POSTFIELDS])) {
            $post = $task['opt'][CURLOPT_POSTFIELDS];
        }
        $file = rtrim($config['dir'], '/') . '/';
        $file .= $this->getCacheFile($url, $post);
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
            file_put_contents($file, gzcompress(serialize($data), $config['compress']), LOCK_EX);
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
