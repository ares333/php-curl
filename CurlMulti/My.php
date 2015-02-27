<?php
/**
 * CurlMulti wrapper, more easy to use 
 * 
 * @author admin@phpdr.net
 *
 */
class CurlMulti_My {
	private $curl;
	function __construct() {
		$this->curl = new CurlMulti_Core ();
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
	 * replace curlmulti use yours
	 *
	 * @param unknown $curlmulti        	
	 */
	function setCurl($curlmulti) {
		$this->curl = $curlmulti;
	}
	
	/**
	 * 16^3=4096,4096^2=16777216,4096^3=68719476736
	 *
	 * @param string $name        	
	 * @param number $level        	
	 * @return string relative path
	 */
	function hashpath($name, $level = 2) {
		$file = md5 ( $name );
		if ($level == 1) {
			$file = substr ( $file, 0, 3 ) . '/' . substr ( $file, 3 );
		} elseif ($level == 2) {
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
	 * default CurlMulti fail callback
	 *
	 * @param array $error        	
	 * @param mixed $args
	 *        	args in CurlMulti::add()
	 */
	function cbCurlFail($error, $args) {
		$err = $error ['error'];
		echo "\nCurl error $err[0]: $err[1], url=" . $error ['info'] ['url'] . "\n";
	}
	
	/**
	 * default CurlMulti info callback
	 *
	 * @param array $info
	 *        	array('all'=>array(),'running'=>array())
	 */
	function cbCurlInfo($info) {
		$str = $this->curlInfoString ( $info );
		if (PHP_OS == 'Linux') {
			$str = "\r\33[K" . trim ( $str );
		} else {
			$str = "\r" . $str;
		}
		echo $str;
	}
	
	/**
	 * CurlMulti info callback string
	 *
	 * @param unknown $info        	
	 */
	protected function curlInfoString($info) {
		$all = $info ['all'];
		$cacheNum = $all ['cacheNum'];
		$taskPoolNum = $all ['taskPoolNum'];
		$finishNum = $all ['finishNum'];
		$speed = round ( $all ['downloadSpeed'] / 1024 ) . 'KB/s';
		$size = round ( $all ['downloadSize'] / 1024 / 1024 ) . "MB";
		$str = '';
		$str .= sprintf ( "speed:%-10s", $speed );
		$str .= sprintf ( 'download:%-10s', $size );
		$str .= sprintf ( 'cache:%-10dfinish:%-10d', $cacheNum, $finishNum );
		$str .= sprintf ( 'taskPool:%-10d', $taskPoolNum );
		foreach ( $all ['taskRunningNumType'] as $k => $v ) {
			$str .= sprintf ( 'running' . $k . ':%-10d', $all ['taskRunningNumType'] [$k] );
		}
		$str .= sprintf ( 'running:%-10d', $all ['taskRunningNumNoType'] );
		return $str;
	}
	
	/**
	 * none http 200 go CurlMulti::maxTry loop
	 *
	 * @param array $info        	
	 * @return boolean
	 */
	function hasHttpError($info) {
		if ($info ['http_code'] != 200) {
			$this->curl->error ( 'http error ' . $info ['http_code'] );
			return true;
		}
		return false;
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
	function encoding($html, $in = null, $out = 'UTF-8', $mode = 'auto') {
		$valid = array (
				'auto',
				'iconv',
				'mb_convert_encoding' 
		);
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
				$in = null;
			}
		}
		if (isset ( $in )) {
			$html = call_user_func ( $func, $in, $out . '//IGNORE', $html );
			$html = preg_replace ( $pattern, "\\1$out\\4", $html, 1 );
		}
		return $html;
	}
	
	/**
	 * is a full url
	 *
	 * @param unknown $str        	
	 * @return boolean
	 */
	function isUrl($str) {
		return in_array ( substr ( $str, 0, 7 ), array (
				'http://',
				'https:/' 
		) );
	}
	
	/**
	 * urlCurrent should be redirected final url.Final url normally has '/' suffix.
	 *
	 * @param unknown $uri
	 *        	uri in the html
	 * @param unknown $urlCurrent
	 *        	redirected final url of the html page
	 * @return string
	 */
	function uri2url($uri, $urlCurrent) {
		if ($this->isUrl ( $uri )) {
			return $uri;
		}
		if (! $this->isUrl ( $urlCurrent )) {
			throw new Exception ( 'url is invalid, url=' . $urlCurrent );
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
	 * @param unknown $url        	
	 * @param unknown $urlCurrent
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
			if ($parse1 [$v] != $parse2 [$v]) {
				$eq = false;
				break;
			}
		}
		$path = null;
		if ($eq) {
			$len = strlen ( $urlDir ) - strlen ( parse_url ( $urlDir, PHP_URL_PATH ) );
			$path1 = substr ( $url, $len + 1 );
			$path2 = substr ( $urlDir, $len + 1 );
			$arr1 = explode ( '/', rtrim ( $path1, '/' ) );
			$arr2 = explode ( '/', rtrim ( $path2, '/' ) );
			foreach ( $arr1 as $k => $v ) {
				if (array_key_exists ( $k, $arr2 ) && $v == $arr2 [$k]) {
					unset ( $arr1 [$k], $arr2 [$k] );
				} else {
					break;
				}
			}
			$count1 = count ( $arr1 );
			$count2 = count ( $arr2 );
			if ($count1 > $count2) {
				$path = implode ( '/', $arr1 );
			} else {
				$path = '';
				foreach ( $arr2 as $v ) {
					$path .= '../';
				}
				$path .= implode ( '/', $arr1 );
			}
		}
		return $path;
	}
	
	/**
	 * urlCurrent should be redirected final url.Final url normally has '/' suffix.
	 *
	 * @param unknown $url
	 *        	the final directed url
	 * @return string
	 */
	function urlDir($url) {
		if (! $this->isUrl ( $url )) {
			throw new Exception ( 'url is invalid, url=' . $url );
		}
		$urlDir = $url;
		// none / end url should be finally redirected to / ended url
		if ('/' != substr ( $urlDir, - 1 )) {
			$urlDir = dirname ( $urlDir ) . '/';
		}
		return $urlDir;
	}
	
	/**
	 * get CurlMulti instance
	 *
	 * @return CurlMulti
	 */
	function getCurl() {
		return $this->curl;
	}
}