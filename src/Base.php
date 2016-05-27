<?php

namespace Ares333\CurlMulti;

/**
 * CurlMulti_Core wrapper, more easy to use
 *
 * @author admin@phpdr.net
 *
 */
class Base {
	private $curl;
	// array('prefix'=>array(array(0,'PRE'),100),'suffix'=>null)
	public $cbInfoFix = array (
			'prefix' => array (),
			'suffix' => array ()
	);
	function __construct($curlmulti = null) {
		if (isset ( $curlmulti )) {
			$this->curl = $curlmulti;
		} else {
			$this->curl = new Core ();
		}
		// default fail callback
		$this->curl->cbFail = array (
				$this,
				'cbCurlFail'
		);
		// default info callback
		$this->curl->cbInfo = array (
				$this,
				'cbCurlInfo'
		);
	}

	/**
	 * 16^3=4096,4096^2=16777216,4096^3=68719476736
	 *
	 * @param string $name
	 * @param integer $level
	 * @return string relative path
	 */
	function hashpath($name, $level = 2) {
		$file = md5 ( $name );
		if ($level == 1) {
		} elseif ($level == 2) {
			$file = substr ( $file, 0, 3 ) . '/' . substr ( $file, 3 );
		} elseif ($level == 3) {
			$file = substr ( $file, 0, 3 ) . '/' . substr ( $file, 3, 6 ) . '/' . substr ( $file, 6 );
		} else {
			throw new Exception ( 'level is invalid, level=' . $level );
		}
		return $file;
	}

	/**
	 * content between start and end
	 *
	 * @param string $str
	 * @param string $start
	 * @param string $end
	 * @param String $mode
	 *        	g greed
	 *        	ng non-greed
	 * @return string boolean
	 */
	function substr($str, $start, $end = null, $mode = 'g') {
		if (isset ( $start )) {
			$pos1 = strpos ( $str, $start );
		} else {
			$pos1 = 0;
		}
		if (isset ( $end )) {
			if ($mode == 'g') {
				$pos2 = strrpos ( $str, $end );
			} elseif ($mode == 'ng') {
				$pos2 = strpos ( $str, $end, $pos1 );
			} else {
				throw new Exception ( 'mode is invalid, mode=' . $mode );
			}
		} else {
			$pos2 = strlen ( $str );
		}
		if (false === $pos1 || false === $pos2 || $pos2 < $pos1) {
			return false;
		}
		$len = strlen ( $start );
		return substr ( $str, $pos1 + $len, $pos2 - $pos1 - $len );
	}

	/**
	 * default CurlMulti_Core fail callback
	 *
	 * @param array $error
	 * @param mixed $args
	 *        	args in CurlMulti_Core::add()
	 */
	function cbCurlFail($error, $args) {
		$err = $error ['error'];
		echo "\nCurl error $err[0]: $err[1], url=" . $error ['info'] ['url'];
	}

	/**
	 * write message to IPC
	 */
	function cbCurlInfoIPC($info, $isFirst, $isLast) {
		$pid = posix_getpid ();
		$this->cbInfoFix ['prefix'] [] = array (
				array (
						0,
						'PID'
				),
				'#' . $pid
		);
		ob_start ();
		$res = $this->cbCurlInfo ( $info, $isFirst, $isLast, false );
		ob_end_clean ();
		$msg = array (
				'isLast' => $isLast,
				'pid' => $pid,
				'caption' => $res ['caption'],
				'content' => $res ['content'],
				'output' => $res ['output']
		);
		while ( false === msg_send ( $this->getMessageQueue () [0], 1, $msg, true, false, $errorCode ) ) {
			if ($errorCode != MSG_EAGAIN) {
				user_error ( 'IPC failed to send message, errorCode=' . $errorCode, E_USER_WARNING );
			} else {
				break;
			}
		}
	}

	/**
	 * get IPC message and display
	 *
	 * @param callback $callback
	 *        	return true to end the loop, called on every loop
	 */
	function curlInfoIPC($callback = null) {
		$queue = $this->getMessageQueue ( true ) [0];
		$nc = ncurses_init ();
		$window = ncurses_newwin ( 0, 0, 0, 0 );
		$caption = '';
		$list = array ();
		$lastClear = time ();
		while ( true ) {
			$time = time ();
			if ($time - $lastClear > 10) {
				ncurses_clear ();
				$lastClear = $time;
			}
			$error = '';
			if (msg_receive ( $queue, 0, $msgtype, 1024 * 1024, $msg, true, MSG_IPC_NOWAIT, $errorCode )) {
				if (strlen ( $msg ['caption'] ) > strlen ( $caption )) {
					$caption = $msg ['caption'];
				}
				$pid = $msg ['pid'];
				$isLast = $msg ['isLast'];
				unset ( $msg ['pid'], $msg ['isLast'], $msg ['caption'] );
				$list [$pid] = $msg;
				if (true === $isLast) {
					unset ( $list [$pid] );
					ncurses_clear ();
					if (empty ( $list )) {
						break;
					}
				}
			} else {
				if ($errorCode != MSG_ENOMSG) {
					$error = 'IPC receive error, errorCode=' . $errorCode;
				}
			}
			$content = '';
			$output = '';
			foreach ( $list as $k => $v ) {
				$content .= $v ['content'] . "\n";
				$output .= $v ['output'] . "\n";
			}
			$str = trim ( $caption . "\n" . $content . "\n" . $output . "\n" . $error );
			ncurses_move ( 0, 0 );
			ncurses_addstr ( $str );
			ncurses_refresh ();
			if (isset ( $callback )) {
				if (call_user_func ( $callback )) {
					break;
				}
			}
			usleep ( 100 * 1000 );
		}
		ncurses_end ();
		msg_remove_queue ( $queue );
	}

	/**
	 * get IPC message queue
	 *
	 * @return resource
	 */
	private function getMessageQueue($remove = false) {
		static $queue;
		if (! isset ( $queue )) {
			$queue = array ();
			$key = ftok ( __FILE__, 'C' );
			$queue [] = msg_get_queue ( $key );
			$queue [] = $key;
			if ($remove) {
				while ( msg_stat_queue ( $queue [0] ) ['msg_qnum'] > 0 ) {
					$errorCode = null;
					if (! msg_receive ( $queue [0], 0, $msgtype, 1024 * 1024, $message, false, $errorCode )) {
						if ($errorCode === MSG_ENOMSG) {
							break;
						} else {
							user_error ( 'IPC can not receive message, errorCode=' . $errorCode, E_USER_WARNING );
							break;
						}
					}
				}
			}
		}
		return $queue;
	}

	/**
	 * default CurlMulti_Core info callback
	 *
	 * @param array $info
	 *        	array('all'=>array(),'running'=>array())
	 * @param bool $isFirst
	 * @param bool $isLast
	 * @param bool $useOb
	 *
	 */
	function cbCurlInfo($info, $isFirst, $isLast, $useOb = true) {
		static $meta = array (
				'downloadSpeed' => array (
						0,
						'SPD'
				),
				'downloadSize' => array (
						0,
						'DWN'
				),
				'finishNum' => array (
						0,
						'FNH'
				),
				'cacheNum' => array (
						0,
						'CAC'
				),
				'taskRunningNum' => array (
						0,
						'TKR'
				),
				'activeNum' => array (
						0,
						'ACT'
				),
				'taskPoolNum' => array (
						0,
						'TKP'
				),
				'queueNum' => array (
						0,
						'QUE'
				),
				'taskNum' => array (
						0,
						'TSK'
				),
				'taskFailNum' => array (
						0,
						'TKF'
				)
		);
		// meta running num of task type
		static $metaType = array ();
		$all = $info ['all'];
		// meta prefix suffix
		foreach ( $this->cbInfoFix as $k => $v ) {
			foreach ( $v as $v1 ) {
				if (! array_key_exists ( $v1 [0] [1], $meta )) {
					if ($k === 'prefix') {
						$meta = array_merge ( array (
								$v1 [0] [1] => $v1 [0]
						), $meta );
					} else {
						$meta [$v1 [0] [1]] = $v1 [0];
					}
				}
				$all [$v1 [0] [1]] = $v1 [1];
			}
		}
		$all ['downloadSpeed'] = round ( $all ['downloadSpeed'] / 1024 ) . 'KB';
		$all ['downloadSize'] = round ( $all ['downloadSize'] / 1024 / 1024 ) . "MB";
		if (! empty ( $all ['taskRunningNumType'] )) {
			foreach ( $all ['taskRunningNumType'] as $k => $v ) {
				$key = 'T(' . $k . ')';
				$all [$key] = $v;
				if (! array_key_exists ( $key, $metaType )) {
					$metaType [$key] = array (
							0,
							$key
					);
				}
			}
		}
		// clean
		foreach ( array_keys ( $meta ) as $v ) {
			if (! array_key_exists ( $v, $all )) {
				unset ( $meta [$v] );
			}
		}
		$content = '';
		$lenPad = 2;
		$caption = '';
		foreach ( array (
				'meta',
				'metaType'
		) as $name ) {
			foreach ( $$name as $k => $v ) {
				if (! isset ( $all [$k] )) {
					continue;
				}
				if (mb_strlen ( $all [$k] ) > $v [0]) {
					$v [0] = mb_strlen ( $all [$k] );
				}
				if (PHP_OS == 'Linux') {
					if (mb_strlen ( $v [1] ) > $v [0]) {
						$v [0] = mb_strlen ( $v [1] );
					}
					$caption .= sprintf ( '%-' . ($v [0] + $lenPad) . 's', $v [1] );
					$content .= sprintf ( '%-' . ($v [0] + $lenPad) . 's', $all [$k] );
				} else {
					$content .= sprintf ( '%-' . ($v [0] + strlen ( $v [1] ) + 1 + $lenPad) . 's', $v [1] . ':' . $all [$k] );
				}
				${$name} [$k] = $v;
			}
		}
		if ($useOb) {
			$output = ob_get_clean ();
		} else {
			$output = '';
		}
		$pre = $output;
		if (PHP_OS == 'Linux') {
			if (! empty ( $pre ) && ! $isFirst) {
				$pre .= "\n\n";
			}
			$str = $pre . "\33[A\r\33[K" . $caption . "\n\r\33[K" . rtrim ( $content );
		} else {
			if (! empty ( $pre ) && ! $isFirst) {
				$pre .= "\n";
			}
			$str = $pre . "\r" . rtrim ( $content );
		}
		if ($isFirst) {
			$str = "\n" . $str;
		}
		if ($isLast) {
			$str .= "\n";
		}
		echo $str;
		if (! $isLast && $useOb) {
			ob_start ();
		}
		return array (
				'caption' => $caption,
				'content' => $content,
				'output' => trim ( $output )
		);
	}

	/**
	 * html encoding transform
	 *
	 * @param string $html
	 * @param string $in
	 * @param string $out
	 * @param string $content
	 * @param string $mode
	 *        	auto|iconv|mb_convert_encoding
	 * @return string
	 */
	function encoding($html, $in = null, $out = null, $mode = 'auto') {
		$valid = array (
				'auto',
				'iconv',
				'mb_convert_encoding'
		);
		if (! isset ( $out )) {
			$out = 'UTF-8';
		}
		if (! in_array ( $mode, $valid )) {
			throw new Exception ( 'invalid mode, mode=' . $mode );
		}
		if (function_exists ( 'iconv' ) && ($mode == 'auto' || $mode == 'iconv')) {
			$func = 'iconv';
		} elseif (function_exists ( 'mb_convert_encoding' ) && ($mode == 'auto' || $mode == 'mb_convert_encoding')) {
			$func = 'mb_convert_encoding';
		} else {
			throw new Exception ( 'charsetTrans failed, no function' );
		}
		$pattern = '/(<meta[^>]*?charset=(["\']?))([a-z\d_\-]*)(\2[^>]*?>)/is';
		if (! isset ( $in )) {
			$n = preg_match ( $pattern, $html, $in );
			if ($n > 0) {
				$in = $in [3];
			} else {
				if (function_exists ( 'mb_detect_encoding' )) {
					$in = mb_detect_encoding ( $html );
				} else {
					$in = null;
				}
			}
		}
		if (isset ( $in )) {
			$old = error_reporting ( error_reporting () & ~ E_NOTICE );
			$html = call_user_func ( $func, $in, $out . '//IGNORE', $html );
			error_reporting ( $old );
			$html = preg_replace ( $pattern, "\\1$out\\4", $html, 1 );
		}
		return $html;
	}

	/**
	 * is a full url
	 *
	 * @param string $str
	 * @return boolean
	 */
	function isUrl($str) {
		$str = ltrim ( $str );
		return in_array ( substr ( $str, 0, 7 ), array (
				'http://',
				'https:/'
		) );
	}

	/**
	 * urlCurrent should be redirected final url.Final url normally has '/' suffix.
	 *
	 * @param string $uri
	 *        	uri in the html
	 * @param string $urlCurrent
	 *        	redirected final url of the page
	 * @return string
	 */
	function uri2url($uri, $urlCurrent) {
		if (empty ( $uri )) {
			return $urlCurrent;
		}
		if ($this->isUrl ( $uri )) {
			return $uri;
		}
		if (! $this->isUrl ( $urlCurrent )) {
			throw new Exception ( 'url is invalid, url=' . $urlCurrent );
		}
		// uri started with ?,#
		if (0 === strpos ( $uri, '#' ) || 0 === strpos ( $uri, '?' )) {
			if (false !== ($pos = strpos ( $urlCurrent, '#' ))) {
				$urlCurrent = substr ( $urlCurrent, 0, $pos );
			}
			if (false !== ($pos = strpos ( $urlCurrent, '?' ))) {
				$urlCurrent = substr ( $urlCurrent, 0, $pos );
			}
			return $urlCurrent . $uri;
		}
		if (0 === strpos ( $uri, './' )) {
			$uri = substr ( $uri, 2 );
		}
		$urlDir = $this->urlDir ( $urlCurrent );
		if (0 === strpos ( $uri, '/' )) {
			$len = strlen ( parse_url ( $urlDir, PHP_URL_PATH ) );
			return substr ( $urlDir, 0, 0 - $len ) . $uri;
		} else {
			return $urlDir . $uri;
		}
	}

	/**
	 * get relative uri of the current page.
	 * urlCurrent should be redirected final url.Final url normally has '/' suffix.
	 *
	 * @param string $url
	 * @param string $urlCurrent
	 *        	redirected final url of the html page
	 * @return string
	 */
	function url2uri($url, $urlCurrent) {
		if (! $this->isUrl ( $url )) {
			throw new Exception ( 'url is invalid, url=' . $url );
		}
		$urlDir = $this->urlDir ( $urlCurrent );
		$parse1 = parse_url ( $url );
		$parse2 = parse_url ( $urlDir );
		if (! array_key_exists ( 'port', $parse1 )) {
			$parse1 ['port'] = null;
		}
		if (! array_key_exists ( 'port', $parse2 )) {
			$parse2 ['port'] = null;
		}
		$eq = true;
		foreach ( array (
				'scheme',
				'host',
				'port'
		) as $v ) {
			if (isset ( $parse1 [$v] ) && isset ( $parse2 [$v] )) {
				if ($parse1 [$v] != $parse2 [$v]) {
					$eq = false;
					break;
				}
			}
		}
		$path = null;
		if ($eq) {
			$len = strlen ( $urlDir ) - strlen ( parse_url ( $urlDir, PHP_URL_PATH ) );
			$path1 = substr ( $url, $len + 1 );
			$path2 = substr ( $urlDir, $len + 1 );
			$arr1 = $arr2 = array ();
			if (! empty ( $path1 )) {
				$arr1 = explode ( '/', rtrim ( $path1, '/' ) );
			}
			if (! empty ( $path2 )) {
				$arr2 = explode ( '/', rtrim ( $path2, '/' ) );
			}
			foreach ( $arr1 as $k => $v ) {
				if (array_key_exists ( $k, $arr2 ) && $v == $arr2 [$k]) {
					unset ( $arr1 [$k], $arr2 [$k] );
				} else {
					break;
				}
			}
			$path = '';
			foreach ( $arr2 as $v ) {
				$path .= '../';
			}
			$path .= implode ( '/', $arr1 );
		}
		return $path;
	}

	/**
	 * url should be redirected final url.Final url normally has '/' suffix.
	 *
	 * @param string $url
	 *        	the final directed url
	 * @return string
	 */
	function urlDir($url) {
		if (! $this->isUrl ( $url )) {
			throw new Exception ( 'url is invalid, url=' . $url );
		}
		$parse = parse_url ( $url );
		$urlDir = $url;
		if (isset ( $parse ['path'] )) {
			// none / end url should be finally redirected to / ended url
			if ('/' != substr ( $urlDir, - 1 )) {
				$urlDir = dirname ( $urlDir ) . '/';
			}
		}
		return $urlDir;
	}

	/**
	 * get CurlMulti\Core instance
	 *
	 * @return \Ares333\CurlMulti\Core
	 */
	function getCurl() {
		return $this->curl;
	}
}