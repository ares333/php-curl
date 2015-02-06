<?php
/**
 * CurlMulti wrapper, more easy to use 
 * @author admin@curlmulti.com
 *
 */
class MyCurl {
	private $curl;
	function __construct() {
		$this->curl = new CurlMulti ();
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
	function substr($str, $start, $end, $mode = 'g') {
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
		$all = $info ['all'];
		$cacheNum = $all ['cacheNum'];
		$taskPoolNum = $all ['taskPoolNum'];
		$finishNum = $all ['finishNum'];
		$speed = round ( $all ['downloadSpeed'] / 1024 ) . 'KB/s';
		$size = round ( $all ['downloadSize'] / 1024 / 1024 ) . "MB";
		$str = "\r";
		$str .= sprintf ( "speed:%-10s", $speed );
		$str .= sprintf ( 'download:%-10s', $size );
		$str .= sprintf ( 'cache:%-10dfinish:%-10d', $cacheNum, $finishNum );
		$str .= sprintf ( 'taskPool:%-10d', $taskPoolNum );
		foreach ( $all ['taskRunningNumType'] as $k => $v ) {
			$str .= sprintf ( 'running' . $k . ':%-10d', $all ['taskRunningNumType'] [$k] );
		}
		$str .= sprintf ( 'running:%-10d', $all ['taskRunningNumNoType'] );
		echo $str;
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
	function encoding($html, $in, $out = 'UTF-8', $mode = 'auto') {
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
		$html = call_user_func ( $func, $in, $out . '//IGNORE', $html );
		return preg_replace ( '/(<meta[^>]*?charset=(["\']?))[a-z\d_\-]*(\2[^>]*?>)/is', "\\1$out\\3", $html, 1 );
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