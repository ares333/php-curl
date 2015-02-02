<?php
/**
 * CurlMulti的封装，更易于使用
 * @author admin@curlmulti.com
 *
 */
class MyCurl {
	protected $curl;
	private $urlFullPath;
	private $urlFullSite;
	
	/**
	 * CurlMulti对象
	 *
	 * @param unknown $curlmulti        	
	 */
	function __construct($curlmulti) {
		$this->curl = $curlmulti;
		// 设置最大并发数
		$this->curl->maxThread = 10;
		// 默认错误回调
		$this->curl->cbFail = array (
				$this,
				'cbCurlFail' 
		);
		// 默认信息回调
		$this->curl->cbInfo = array (
				$this,
				'cbCurlInfo' 
		);
	}
	
	/**
	 * 每个目录最多4096个文件(可以保持很好的IO性能),4096^2=16777216,4096^3=68719476736
	 *
	 * @param string $name        	
	 * @param number $level       	
	 * @return string 文件相对路径
	 */
	function hashPath($name, $level = 2) {
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
	 * 返回开始和结束字符串之间的字符串
	 *
	 * @param string $str        	
	 * @param string $start
	 *        	开始字符串
	 * @param string $end
	 *        	结束字符串
	 * @param String $mode
	 *        	g 贪婪模式
	 *        	ng 非贪婪模式
	 * @return string boolean
	 */
	function subStr($str, $start, $end, $mode = 'g') {
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
	 * 默认CurlMulti错误回调
	 *
	 * @param array $error        	
	 * @param mixed $args
	 *        	用户添加任务时的参数
	 */
	function cbCurlFail($error, $args) {
		$err = $error ['error'];
		echo "\ncurl error, $err[0] : $err[1]\n";
	}
	
	/**
	 * 默认CurlMulti的信息回调
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
	 * 处理http头不是200的请求
	 *
	 * @param array $info        	
	 * @return boolean 是否有错
	 */
	function httpError($info) {
		if ($info ['http_code'] != 200) {
			$this->curl->error ( 'http error, code=' . $info ['http_code'] . ', url=' . $info ['url'] );
			return true;
		}
		return false;
	}
	
	/**
	 * html转码
	 *
	 * @param string $html        	
	 * @param string $in        	
	 * @param string $out        	
	 * @param string $content        	
	 * @param string $mode
	 *        	auto|iconv|mb_convert_encoding
	 * @return string
	 */
	function charsetTrans($html, $in, $out = 'UTF-8', $mode = 'auto') {
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
	 * 把html中的所有相对地址换成绝对地址
	 *
	 * @param string $html        	
	 * @param string $url        	
	 * @return string string
	 */
	function urlFull($html, $url) {
		$parseUrl = parse_url ( $url );
		$this->urlFullPath = str_replace ( '\\', '/', dirname ( $parseUrl ['path'] ) );
		$this->urlFullSite = $parseUrl ['scheme'] . '://' . $parseUrl ['host'];
		return preg_replace_callback ( '/(\s+(src|href)=("|\')?)(?!http:\/\/)([^\\3]+?)\\3/i', array (
				$this,
				'_cbUrlFull' 
		), $html );
	}
	
	/**
	 * urlFull的正则回调
	 *
	 * @param unknown $match        	
	 * @param unknown $uri        	
	 * @return string
	 */
	final function _cbUrlFull($match) {
		if ((0 === stripos ( $match [4], 'javascript:' )) || (0 === stripos ( $match [4], '#' ))) {
			return $match [0];
		}
		if (0 !== strpos ( $match [4], '/' )) {
			$match [4] = $this->urlFullPath . '/' . $match [4];
		}
		$match [4] = ltrim ( $match [4], '/' );
		return $match [1] . $this->urlFullSite . '/' . $match [4] . $match [3];
	}
	
	/**
	 * 返回CurlMulti对象
	 *
	 * @return CurlMulti
	 */
	function getCurl() {
		return $this->curl;
	}
}