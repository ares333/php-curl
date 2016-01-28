<?php

namespace Ares333\CurlMulti;

/**
 * Chrome Mozilla/5.0 (Windows NT 6.1) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.47 Safari/536.11
 * IE6 Mozilla/5.0 (Windows NT 6.1; rv:9.0.1) Gecko/20100101 Firefox/9.0.1
 * FF Mozilla/5.0 (Windows NT 6.1; WOW64; rv:24.0) Gecko/20100101 Firefox/24.0
 *
 * more useragent:http://www.useragentstring.com/
 *
 * @author admin@phpdr.net
 *
 */
class Core {
	// handler
	const TASK_CH = 0x01;
	// arguments
	const TASK_ITEM_ARGS = 0x02;
	// operation, task level
	const TASK_ITEM_OPT = 0x03;
	// control options
	const TASK_ITEM_CTL = 0x04;
	// success callback
	const TASK_PROCESS = 0x05;
	// curl fail callback
	const TASK_FAIL = 0x06;
	// tryed times
	const TASK_TRYED = 0x07;

	// global max thread num
	public $maxThread = 10;
	// Max thread by task type.Task type is specified in $item['ctl'] in add().If task has no type,$this->maxThreadNoType is maxThread-sum(maxThreadType).If less than 0 $this->maxThreadNoType is set to 0.
	public $maxThreadType = array ();
	// retry time(s) when task failed
	public $maxTry = 3;
	// operation, class level curl opt
	public $opt = array (
			CURLINFO_HEADER_OUT => true,
			CURLOPT_HEADER => true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_AUTOREFERER => true,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.86 Safari/537.36',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 5
	);
	// cache options,dirLevel values is less than 3
	public $cache = array (
			'enable' => false,
			'enableDownload' => false,
			'compress' => false,
			'dir' => null,
			'dirLevel' => 1,
			'expire' => 86400,
			'verifyPost' => false,
			'overwrite' => false,
			'overwriteExpire' => 86400
	);
	// stack or queue
	public $taskPoolType = 'queue';
	// eliminate duplicate for taskpool, will delete previous task and add new one
	public $taskOverride = false;
	// task callback,add() should be called in callback, $cbTask[0] is callback, $cbTask[1] is param.
	public $cbTask;
	// status callback
	public $cbInfo;
	// user callback
	public $cbUser;
	// common fail callback, called if no one specified
	public $cbFail;

	// is the loop running
	protected $isRunning = false;
	// max thread num no type
	protected $maxThreadNoType;
	// all added task was saved here first
	protected $taskPool = array ();
	// taskPool with high priority
	protected $taskPoolAhead = array ();
	// running task(s)
	protected $taskRunning = array ();
	// failed task need to retry
	protected $taskFail = array ();

	// handle of multi-thread curl
	private $mh;
	// if __construct called
	private $isConstructCalled = false;
	// running info
	private $info = array (
			'all' => array (
					// the real multi-thread num
					'activeNum' => 0,
					// finished task in the queue
					'queueNum' => 0,
					// network speed, bytes
					'downloadSpeed' => 0,
					// byte
					'downloadSize' => 0,
					// byte
					'headerSize' => 0,
					// finished task number,include failed task and cache
					'finishNum' => 0,
					// The number of cache used
					'cacheNum' => 0,
					// completely failed task number
					'failNum' => 0,
					// task num has added
					'taskNum' => 0,
					// $this->taskRunning size
					'taskRunningNum' => 0,
					// task running num by type,
					'taskRunningNumType' => array (),
					// task ruuning num no type
					'taskRunningNumNoType' => 0,
					// $this->taskPool size
					'taskPoolNum' => 0,
					// $this->taskFail size
					'taskFailNum' => 0,
					// finish percent
					'finishPercent' => 0
			),
			'running' => array ()
	);
	private $sizeProcessed = 0;
	private static $instance;

	/**
	 *
	 * @throws Exception
	 */
	function __construct() {
		$this->isConstructCalled = true;
		if (version_compare ( PHP_VERSION, '5.1.0' ) < 0) {
			throw new Exception ( 'PHP 5.1.0+ is needed' );
		}
	}

	/**
	 * get singleton instance
	 *
	 * @return self
	 */
	static function getInstance() {
		if (! isset ( static::$instance )) {
			static::$instance = new self ();
		}
		return static::$instance;
	}

	/**
	 * add a task to taskPool
	 *
	 * @param array $item
	 *        	array('url'=>'',['opt'=>array(),['args'=>array(),['ctl'=>array('type'=>'','ahead'=>false,'cache'=>array())]]]])
	 * @param mixed $process
	 *        	success callback,for callback first param array('info'=>,'content'=>), second param $item[args]
	 * @param mixed $fail
	 *        	curl fail callback,for callback first param array('error'=>array(0=>code,1=>msg),'info'=>array),second param $item[args];
	 * @throws Exception
	 * @return self
	 */
	function add(array $item, $process = null, $fail = null) {
		// check
		if (! is_array ( $item )) {
			throw new Exception ( 'item must be array, item is ' . gettype ( $item ) );
		}
		if (! isset ( $item ['url'] ) && ! empty ( $item ['opt'] [CURLOPT_URL] )) {
			$item ['url'] = $item ['opt'] [CURLOPT_URL];
		}
		$item ['url'] = trim ( $item ['url'] );
		if (empty ( $item ['url'] )) {
			throw new Exception ( 'url can\'t be empty, url=' . $item ['url'] );
		}
		// replace space with + to avoid some curl problems
		$item ['url'] = str_replace ( ' ', '+', $item ['url'] );
		if (array_key_exists ( 'fragment', parse_url ( $item ['url'] ) )) {
			$pos = strrpos ( $item ['url'], '#' );
			$item ['url'] = substr ( $item ['url'], 0, $pos );
		}
		// fix
		if (empty ( $item ['opt'] )) {
			$item ['opt'] = array ();
		}
		$item ['opt'] [CURLOPT_URL] = $item ['url'];
		if (! array_key_exists ( 'args', $item ))
			$item ['args'] = array ();
		if (empty ( $item ['ctl'] )) {
			$item ['ctl'] = array ();
		}
		if (empty ( $item ['ctl'] ['cache'] )) {
			$item ['ctl'] ['cache'] = array ();
		}
		if (! isset ( $item ['ctl'] ['ahead'] )) {
			$item ['ctl'] ['ahead'] = false;
		}
		if (empty ( $process )) {
			$process = null;
		}
		if (empty ( $fail )) {
			$fail = null;
		}
		$task = array ();
		$task [self::TASK_ITEM_ARGS] = array (
				$item ['args']
		);
		$task [self::TASK_ITEM_OPT] = $item ['opt'];
		$task [self::TASK_ITEM_CTL] = $item ['ctl'];
		$task [self::TASK_PROCESS] = $process;
		$task [self::TASK_FAIL] = $fail;
		$task [self::TASK_TRYED] = 0;
		$task [self::TASK_CH] = null;
		// uniq
		if ($this->taskOverride) {
			foreach ( array (
					'taskPoolAhead',
					'taskPool'
			) as $v ) {
				foreach ( $this->$v as $k1 => $v1 ) {
					if ($v1 [self::TASK_ITEM_OPT] [CURLOPT_URL] == $task [self::TASK_ITEM_OPT] [CURLOPT_URL]) {
						$t = &$this->$v;
						unset ( $t [$k1] );
					}
				}
			}
		}
		// add
		if (true == $task [self::TASK_ITEM_CTL] ['ahead']) {
			$this->taskPoolAhead [] = $task;
		} else {
			$this->taskPool [] = $task;
		}
		$this->info ['all'] ['taskNum'] ++;
		return $this;
	}

	/**
	 * Perform the actual task(s).
	 *
	 * @param
	 *        	mixed callback control the persist
	 */
	function start($persist = null) {
		if ($this->isRunning) {
			throw new Exception ( __CLASS__ . ' is running !' );
		}
		if (false == $this->isConstructCalled) {
			throw new Exception ( __CLASS__ . ' __construct is not called' );
		}
		$this->mh = curl_multi_init ();
		$this->info ['all'] ['downloadSize'] = 0;
		$this->info ['all'] ['finishNum'] = 0;
		$this->info ['all'] ['cacheNum'] = 0;
		$this->info ['all'] ['failNum'] = 0;
		$this->info ['all'] ['taskNum'] = 0;
		$this->info ['all'] ['taskRunningNumNoType'] = 0;
		$this->setThreadData ();
		$this->isRunning = true;
		$this->addTask ();
		do {
			$this->exec ();
			curl_multi_select ( $this->mh );
			$this->callCbInfo ();
			if (isset ( $this->cbUser )) {
				call_user_func ( $this->cbUser );
			}
			// useful for persist
			$this->addTask ();
			while ( false != ($curlInfo = curl_multi_info_read ( $this->mh, $this->info ['all'] ['queueNum'] )) ) {
				$ch = $curlInfo ['handle'];
				$task = $this->taskRunning [( int ) $ch];
				$info = curl_getinfo ( $ch );
				$this->info ['all'] ['downloadSize'] += $info ['size_download'];
				$this->info ['all'] ['headerSize'] += $info ['header_size'];
				if ($curlInfo ['result'] == CURLE_OK) {
					$param = array ();
					$param ['info'] = $info;
					$param ['ext'] = array ();
					if (! isset ( $task [self::TASK_ITEM_OPT] [CURLOPT_FILE] )) {
						$param ['content'] = curl_multi_getcontent ( $ch );
						if ($task [self::TASK_ITEM_OPT] [CURLOPT_HEADER]) {
							preg_match_all ( "/HTTP\/.+(?=\r\n\r\n)/Usm", $param ['content'], $param ['header'] );
							$param ['header'] = $param ['header'] [0];
							$pos = 0;
							foreach ( $param ['header'] as $v ) {
								$pos += strlen ( $v ) + 4;
							}
							$param ['content'] = substr ( $param ['content'], $pos );
						}
					}
				}
				curl_multi_remove_handle ( $this->mh, $ch );
				// must close first,other wise download may be not commpleted in process callback
				curl_close ( $ch );
				if ($curlInfo ['result'] == CURLE_OK) {
					$userRes = $this->process ( $task, $param );
					if (isset ( $task [self::TASK_ITEM_OPT] [CURLOPT_FILE] )) {
						fclose ( $task [self::TASK_ITEM_OPT] [CURLOPT_FILE] );
					}
				}
				// error handle
				$callFail = false;
				if ($curlInfo ['result'] !== CURLE_OK || ! empty ( $userRes ['error'] )) {
					if ($task [self::TASK_TRYED] >= $this->maxTry) {
						// user error
						if (! empty ( $userRes ['error'] )) {
							$err = array (
									'error' => array (
											CURLE_OK,
											$userRes ['error']
									)
							);
						} else {
							$err = array (
									'error' => array (
											$curlInfo ['result'],
											curl_error ( $ch )
									)
							);
						}
						$err ['info'] = $info;
						if (isset ( $task [self::TASK_FAIL] ) || isset ( $this->cbFail )) {
							array_unshift ( $task [self::TASK_ITEM_ARGS], $err );
							$callFail = true;
						} else {
							user_error ( "Error " . implode ( ', ', $err ['error'] ) . ", url=$info[url]", E_USER_WARNING );
						}
						$this->info ['all'] ['failNum'] ++;
					} else {
						$task [self::TASK_TRYED] ++;
						$task [self::TASK_ITEM_CTL] ['useCache'] = false;
						$this->taskFail [] = $task;
						$this->info ['all'] ['taskNum'] ++;
					}
				}
				if ($callFail) {
					if (isset ( $task [self::TASK_FAIL] )) {
						call_user_func_array ( $task [self::TASK_FAIL], $task [self::TASK_ITEM_ARGS] );
					} elseif (isset ( $this->cbFail )) {
						call_user_func_array ( $this->cbFail, $task [self::TASK_ITEM_ARGS] );
					}
				}
				unset ( $this->taskRunning [( int ) $ch] );
				if (array_key_exists ( 'type', $task [self::TASK_ITEM_CTL] )) {
					$this->info ['all'] ['taskRunningNumType'] [$task [self::TASK_ITEM_CTL] ['type']] --;
				} else {
					$this->info ['all'] ['taskRunningNumNoType'] --;
				}
				$this->info ['all'] ['finishNum'] ++;
				$this->sizeProcessed += $info ['size_download'] + $info ['header_size'];
				$this->addTask ();
				// if $this->info['all']['queueNum'] grow very fast there will be no efficiency lost,because outer $this->exec() won't be executed.
				$this->exec ();
				$this->callCbInfo ();
				if (isset ( $this->cbUser )) {
					call_user_func ( $this->cbUser );
				}
			}
		} while ( $this->info ['all'] ['activeNum'] || $this->info ['all'] ['queueNum'] || ! empty ( $this->taskFail ) || ! empty ( $this->taskRunning ) || ! empty ( $this->taskPool ) || (isset ( $persist ) && true == call_user_func ( $persist, $this )) );
		$this->callCbInfo ( true );
		curl_multi_close ( $this->mh );
		unset ( $this->mh );
		$this->isRunning = false;
	}

	/**
	 * call $this->cbInfo
	 */
	private function callCbInfo($isLast = false) {
		static $downloadStartTime;
		static $downloadSpeed = array ();
		static $lastTime = 0;
		if (! isset ( $downloadStartTime )) {
			$downloadStartTime = time ();
		}
		$downloadSpeedLimit = 3;
		$now = time ();
		if (($isLast || $now - $lastTime > 0) && isset ( $this->cbInfo )) {
			$this->info ['all'] ['taskPoolNum'] = count ( $this->taskPool );
			$this->info ['all'] ['taskRunningNum'] = count ( $this->taskRunning );
			$this->info ['all'] ['taskFailNum'] = count ( $this->taskFail );
			if ($this->info ['all'] ['taskNum'] > 0) {
				$this->info ['all'] ['finishPercent'] = round ( $this->info ['all'] ['finishNum'] / $this->info ['all'] ['taskNum'], 4 );
			}
			// running
			$this->info ['running'] = array ();
			foreach ( $this->taskRunning as $k => $v ) {
				$this->info ['running'] [$k] = curl_getinfo ( $v [self::TASK_CH] );
			}
			if ($now - $downloadStartTime > 0) {
				if (count ( $downloadSpeed ) > $downloadSpeedLimit) {
					array_shift ( $downloadSpeed );
				}
				$downloadSpeed [] = round ( $this->sizeProcessed / ($now - $downloadStartTime) );
				$this->info ['all'] ['downloadSpeed'] = round ( array_sum ( $downloadSpeed ) / count ( $downloadSpeed ) );
			}
			if ($now - $downloadStartTime > $downloadSpeedLimit) {
				$this->sizeProcessed = 0;
				$downloadStartTime = $now;
			}
			call_user_func_array ( $this->cbInfo, array (
					$this->info,
					0 == $lastTime,
					$isLast
			) );
			$lastTime = $now;
		}
	}

	/**
	 * set $this->maxThreadNoType, $this->info['all']['taskRunningNumType'], $this->info['all']['taskRunningNumNoType'] etc
	 */
	private function setThreadData() {
		$this->maxThreadNoType = $this->maxThread - array_sum ( $this->maxThreadType );
		if ($this->maxThreadNoType < 0) {
			$this->maxThreadNoType = 0;
		}
		// unset none exitst type num
		foreach ( $this->info ['all'] ['taskRunningNumType'] as $k => $v ) {
			if ($v == 0 && ! array_key_exists ( $k, $this->maxThreadType )) {
				unset ( $this->info ['all'] ['taskRunningNumType'] [$k] );
			}
		}
		// init type num
		foreach ( $this->maxThreadType as $k => $v ) {
			if ($v == 0) {
				user_error ( 'maxThreadType[' . $k . '] is 0, task of this type will never be added!', E_USER_WARNING );
			}
			if (! array_key_exists ( $k, $this->info ['all'] ['taskRunningNumType'] )) {
				$this->info ['all'] ['taskRunningNumType'] [$k] = 0;
			}
		}
	}

	/**
	 * curl_multi_exec()
	 */
	private function exec() {
		while ( curl_multi_exec ( $this->mh, $this->info ['all'] ['activeNum'] ) === CURLM_CALL_MULTI_PERFORM ) {
		}
	}

	/**
	 * add a task to curl, keep $this->maxThread concurrent automatically
	 */
	private function addTask() {
		$c = $this->maxThread - count ( $this->taskRunning );
		$isTaskPoolAdd = true;
		while ( $c > 0 ) {
			$task = array ();
			// search failed first
			if (! empty ( $this->taskFail )) {
				$task = array_pop ( $this->taskFail );
			} else {
				// cbTask
				if ($isTaskPoolAdd && ! empty ( $this->cbTask ) && empty ( $this->taskPool )) {
					if (! isset ( $this->cbTask [1] )) {
						$this->cbTask [1] = array ();
					}
					call_user_func_array ( $this->cbTask [0], array (
							$this->cbTask [1]
					) );
					if (empty ( $this->taskPool )) {
						$isTaskPoolAdd = false;
					}
				}
				if (! empty ( $this->taskPoolAhead )) {
					$task = array_pop ( $this->taskPoolAhead );
				} elseif (! empty ( $this->taskPool )) {
					if ($this->taskPoolType == 'stack') {
						$task = array_pop ( $this->taskPool );
					} elseif ($this->taskPoolType == 'queue') {
						$task = array_shift ( $this->taskPool );
					} else {
						throw new Exception ( 'taskPoolType not found, taskPoolType=' . $this->taskPoolType );
					}
				}
			}
			$noAdd = false;
			$cache = null;
			if (! empty ( $task )) {
				$cache = $this->cache ( $task );
				if (null !== $cache) {
					// download task
					if (isset ( $task [self::TASK_ITEM_OPT] [CURLOPT_FILE] )) {
						if (flock ( $task [self::TASK_ITEM_OPT] [CURLOPT_FILE], LOCK_EX )) {
							fwrite ( $task [self::TASK_ITEM_OPT] [CURLOPT_FILE], $cache ['content'] );
							flock ( $task [self::TASK_ITEM_OPT] [CURLOPT_FILE], LOCK_UN );
						} else {
							$temp = stream_get_meta_data ( $task [self::TASK_ITEM_OPT] [CURLOPT_FILE] );
							throw new Exception ( 'Can not lock file, file=' . $temp ['uri'] );
						}
						unset ( $cache ['content'] );
					}
					$this->process ( $task, $cache );
					$this->info ['all'] ['cacheNum'] ++;
					$this->info ['all'] ['finishNum'] ++;
					$this->callCbInfo ();
				} else {
					$this->setThreadData ();
					if (array_key_exists ( 'type', $task [self::TASK_ITEM_CTL] ) && ! array_key_exists ( $task [self::TASK_ITEM_CTL] ['type'], $this->maxThreadType )) {
						user_error ( 'task was set to notype because type was not set in $this->maxThreadType, type=' . $task [self::TASK_ITEM_CTL] ['type'], E_USER_WARNING );
						unset ( $task [self::TASK_ITEM_CTL] ['type'] );
					}
					if (array_key_exists ( 'type', $task [self::TASK_ITEM_CTL] )) {
						$maxThread = $this->maxThreadType [$task [self::TASK_ITEM_CTL] ['type']];
						$isNoType = false;
					} else {
						$maxThread = $this->maxThreadNoType;
						$isNoType = true;
					}
					if ($isNoType && $maxThread == 0) {
						user_error ( 'task was disgarded because maxThreadNoType=0, url=' . $task [self::TASK_ITEM_OPT] [CURLOPT_URL], E_USER_WARNING );
					}
					if (($isNoType && $this->info ['all'] ['taskRunningNumNoType'] < $maxThread) || (! $isNoType && $this->info ['all'] ['taskRunningNumType'] [$task [self::TASK_ITEM_CTL] ['type']] < $maxThread)) {
						$task = $this->curlInit ( $task );
						$this->taskRunning [( int ) $task [self::TASK_CH]] = $task;
						if ($isNoType) {
							$this->info ['all'] ['taskRunningNumNoType'] ++;
						} else {
							$this->info ['all'] ['taskRunningNumType'] [$task [self::TASK_ITEM_CTL] ['type']] ++;
						}
						curl_multi_add_handle ( $this->mh, $task [self::TASK_CH] );
					} else {
						// rotate task to pool
						if ($task [self::TASK_TRYED] > 0) {
							array_unshift ( $this->taskFail, $task );
						} else {
							array_unshift ( $this->taskPool, $task );
						}
						$noAdd = true;
					}
				}
			}
			if (null == $cache || $noAdd) {
				$c --;
			}
		}
	}

	/**
	 * do process
	 *
	 * @param array $task
	 * @param array $param
	 * @param boolean $isCache
	 */
	private function process($task, $param) {
		array_unshift ( $task [self::TASK_ITEM_ARGS], $param );
		$userRes = array ();
		if (isset ( $task [self::TASK_PROCESS] )) {
			$userRes = call_user_func_array ( $task [self::TASK_PROCESS], $task [self::TASK_ITEM_ARGS] );
			if (! isset ( $userRes )) {
				$userRes = array ();
			} else if (! is_array ( $userRes )) {
				user_error ( 'return value from cbProcess is not array, type=' . gettype ( $userRes ), E_USER_WARNING );
				$userRes = array ();
			}
		}
		if (is_array ( $userRes )) {
			if (! empty ( $userRes ['cache'] )) {
				$task [self::TASK_ITEM_CTL] ['cache'] = array_merge ( $task [self::TASK_ITEM_CTL] ['cache'], $userRes ['cache'] );
			}
		}
		// process cache
		if (empty ( $param ['ext'] ['cache'] ['file'] )) {
			$this->cache ( $task, $param );
		}
		return $userRes;
	}

	/**
	 * set or get file cache
	 *
	 * @param string $url
	 * @param array|null $content
	 * @return mixed
	 */
	private function cache($task, $content = null) {
		$config = array_merge ( $this->cache, $task [self::TASK_ITEM_CTL] ['cache'] );
		if (! $config ['enable']) {
			return;
		}
		if (! isset ( $config ['dir'] ))
			throw new Exception ( 'Cache dir is not defined' );
		$url = $task [self::TASK_ITEM_OPT] [CURLOPT_URL];
		// verify post
		$suffix = '';
		if (true == $config ['verifyPost'] && ! empty ( $task [self::TASK_ITEM_OPT] [CURLOPT_POSTFIELDS] )) {
			$post = $task [self::TASK_ITEM_OPT] [CURLOPT_POSTFIELDS];
			if (is_array ( $post )) {
				$post = http_build_query ( $post );
			}
			$suffix .= $post;
		}
		$key = md5 ( $url . $suffix );
		// calculate file
		$file = rtrim ( $config ['dir'], '/' ) . '/';
		$isDownload = isset ( $task [self::TASK_ITEM_OPT] [CURLOPT_FILE] );
		if (isset ( $config ['dirLevel'] ) && $config ['dirLevel'] != 0) {
			if ($config ['dirLevel'] == 1) {
				$file .= substr ( $key, 0, 3 ) . '/' . substr ( $key, 3 );
			} elseif ($config ['dirLevel'] == 2) {
				$file .= substr ( $key, 0, 3 ) . '/' . substr ( $key, 3, 3 ) . '/' . substr ( $key, 6 );
			} else {
				throw new Exception ( 'cache dirLevel is invalid, dirLevel=' . $config ['dirLevel'] );
			}
		} else {
			$file .= $key;
		}
		if (! isset ( $content )) {
			if (file_exists ( $file )) {
				$expire = $config ['expire'];
				if (! is_numeric ( $expire )) {
					throw new Exception ( 'cache expire is invalid, expire=' . $expire );
				}
				$time = time ();
				$mtime = filemtime ( $file );
				if ($config ['overwrite']) {
					$overwriteExpire = $config ['overwriteExpire'];
					if (! is_numeric ( $overwriteExpire )) {
						throw new Exception ( 'cache overwrite expire is invalid, expire=' . $overwriteExpire );
					}
					if ($time - $mtime > $overwriteExpire) {
						return;
					}
				}
				if ($time - $mtime < $expire) {
					$r = file_get_contents ( $file );
					if ($config ['compress']) {
						$r = gzuncompress ( $r );
					}
					$r = unserialize ( $r );
					if ($isDownload) {
						$r ['content'] = base64_decode ( $r ['content'] );
					}
					return $r;
				}
			}
		} else {
			if (! isset ( $content ['ext'] ['cache'] )) {
				$content ['ext'] ['cache'] = array ();
			}
			$content ['ext'] ['cache'] ['file'] = $file;
			// check main cache directory
			if (! is_dir ( $config ['dir'] )) {
				throw new Exception ( "Cache dir doesn't exists" );
			} else {
				$dir = dirname ( $file );
				// level 1 subdir
				if (isset ( $config ['dirLevel'] ) && $config ['dirLevel'] > 1) {
					$dir1 = dirname ( $dir );
					if (! is_dir ( $dir1 ) && ! mkdir ( $dir1 )) {
						throw new Exception ( 'Create dir failed, dir=' . $dir1 );
					}
				}
				if (! is_dir ( $dir ) && ! mkdir ( $dir )) {
					throw new Exception ( 'Create dir failed, dir=' . $dir );
				}
				if ($isDownload) {
					$temp = stream_get_meta_data ( $task [self::TASK_ITEM_OPT] [CURLOPT_FILE] );
					$content ['content'] = base64_encode ( file_get_contents ( $temp ['uri'] ) );
				}
				$content = serialize ( $content );
				if ($config ['compress']) {
					$content = gzcompress ( $content );
				}
				if (false === file_put_contents ( $file, $content, LOCK_EX )) {
					throw new Exception ( 'Write cache file failed' );
				}
			}
		}
	}

	/**
	 * get curl handle
	 *
	 * @param array $task
	 * @return array
	 */
	private function curlInit($task) {
		$task [self::TASK_CH] = curl_init ();
		$opt = $this->opt;
		foreach ( $task [self::TASK_ITEM_OPT] as $k => $v ) {
			$opt [$k] = $v;
		}
		curl_setopt_array ( $task [self::TASK_CH], $opt );
		$task [self::TASK_ITEM_OPT] = $opt;
		return $task;
	}
}