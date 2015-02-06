<?php
/**
 * Website copy, keep original directory structure(be supported by sound reason)
 * @author admin@curlmulti.com
 *
 */
class MyCurl_Clone extends MyCurl {
	// overwrite local file
	public $overwrite = false;
	// if download pic
	public $downloadPic = true;
	
	// init url
	private $url;
	// absolute local dir
	private $dir;
	// finished url
	private $urlAdded = array ();
	// all site
	private $site = array ();
	/**
	 *
	 * @param unknown $curlmulti        	
	 * @param unknown $url        	
	 * @param unknown $dir        	
	 */
	function __construct($curlmulti, $url, $dir) {
		parent::__construct ( $curlmulti );
		$this->url = $url;
		$this->dir = $dir;
		if (! $this->isUrl ( $url )) {
			throw new Exception ( 'url is invalid, url=' . $url );
		}
		if (! is_dir ( $this->dir )) {
			throw new Exception ( 'dir not found, dir=' . $this->dir );
		}
	}
	function start() {
		$this->getCurl ()->add ( array (
				'url' => $this->url,
				'args' => array (
						'url' => $this->url, // to prevent 301,302 etc
						'file' => $this->getFile ( $this->url ) 
				) 
		), array (
				$this,
				'cbProcess' 
		) );
		$this->urlAdded [] = $this->url;
		$this->getCurl ()->start ();
		return false;
	}
	/**
	 * download and html callback
	 *
	 * @param unknown $r        	
	 * @param unknown $args        	
	 * @return
	 *
	 */
	function cbProcess($r, $args) {
		if (isset ( $r ['content'] )) {
			if (! $this->hasHttpError ( $r ['info'] )) {
				$urlDir = $r ['info'] ['url'];
				// none / end url will be redirected to / ended url
				if ('/' != substr ( $urlDir, - 1 )) {
					$urlDir = dirname ( $urlDir ) . '/';
				}
				
				$pq = phpQuery::newDocumentHTML ( $r ['content'] );
				$urlDownload = array ();
				// css
				$list = $pq ['link[type$=css]'];
				foreach ( $list as $v ) {
					$v = pq ( $v );
					$url = $this->getUrl ( $v->attr ( 'href' ), $urlDir );
					$v->attr ( 'href', $this->getUri ( $url, $urlDir ) );
					$urlDownload [] = $url;
				}
				// script
				$script = $pq ['script[type$=script]'];
				foreach ( $script as $v ) {
					$v = pq ( $v );
					$url = $this->getUrl ( $v->attr ( 'src' ), $urlDir );
					$v->attr ( 'src', $this->getUri ( $url, $urlDir ) );
					$urlDownload [] = $url;
				}
				// pic
				$pic = $pq ['img'];
				if ($this->downloadPic) {
					foreach ( $pic as $v ) {
						$v = pq ( $v );
						$url = $this->getUrl ( $v->attr ( 'src' ), $urlDir );
						$v->attr ( 'src', $this->getUri ( $url, $urlDir ) );
						$urlDownload [] = $url;
					}
				} else {
					foreach ( $pic as $v ) {
						$v = pq ( $v );
						$v->attr ( 'src', $this->getUrl ( $v->attr ( 'src' ), $urlDir ) );
					}
				}
				// html
				$a = $pq ['a'];
				$urlHtml = array ();
				foreach ( $a as $v ) {
					$v = pq ( $v );
					$url = $this->getUrl ( $v->attr ( 'href' ), $urlDir );
					if (0 === strpos ( $url, $urlDir )) {
						$v->attr ( 'href', $this->getUri ( $url, $urlDir ) );
						$urlHtml [] = $url;
					}
				}
				$r ['content'] = $pq->html ();
				// add
				foreach ( array (
						'urlDownload',
						'urlHtml' 
				) as $v ) {
					$$v = array_unique ( $$v );
					foreach ( $$v as $v1 ) {
						if (! in_array ( $v1, $this->urlAdded )) {
							$file = $this->getFile ( $v1 );
							if (null == $file) {
								continue;
							}
							$item = array (
									'url' => $v1,
									'file' => $file,
									'args' => array (
											'url' => $v1,
											'file' => $file 
									) 
							);
							if ($v == 'urlDownload') {
								unset ( $item ['args'] ['file'] );
							} else {
								unset ( $item ['file'] );
							}
							$this->getCurl ()->add ( $item, array (
									$this,
									'cbProcess' 
							) );
							$this->urlAdded [] = $v1;
						}
					}
				}
				if (isset ( $args ['file'] ) && ! file_put_contents ( $args ['file'], $r ['content'], LOCK_EX )) {
					user_error ( 'write file failed, file=' . $args ['file'], E_USER_WARNING );
				}
			}
		}
	}
	/**
	 *
	 * @param unknown $str        	
	 * @return boolean
	 */
	private function isUrl($str) {
		return in_array ( substr ( $str, 0, 7 ), array (
				'http://',
				'https:/' 
		) );
	}
	/**
	 * get full url
	 *
	 * @param unknown $str        	
	 * @param unknown $urlDir        	
	 * @return string
	 */
	private function getUrl($str, $urlDir) {
		if ($this->isUrl ( $str )) {
			return $str;
		}
		if (0 === strpos ( $str, '/' )) {
			$len = strlen ( parse_url ( $urlDir, PHP_URL_PATH ) );
			return substr ( $urlDir, 0, 0 - $len ) . $str;
		} else {
			return $urlDir . $str;
		}
	}
	
	/**
	 * get relative uri
	 *
	 * @param unknown $url        	
	 * @param unknown $urlDir        	
	 * @return mixed
	 */
	private function getUri($url, $urlDir) {
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
		} else {
			$path1 = $this->getPath ( $url );
			$path2 = ltrim ( parse_url ( $urlDir, PHP_URL_PATH ), '/' );
			$arr2 = explode ( '/', rtrim ( $path2, '/' ) );
			$path = '../';
			foreach ( $arr2 as $v ) {
				$path .= '../';
			}
			$path .= $path1;
		}
		return $path;
	}
	
	/**
	 * compute local absolute path
	 *
	 * @param unknown $url        	
	 * @return string
	 */
	private function getFile($url) {
		$file = $this->getPath ( $url );
		$strrpos = strrpos ( $file, '#' );
		if (false !== $strrpos) {
			$file = substr ( $file, 0, $strrpos );
		}
		$file = $this->dir . '/' . $file;
		$dir = dirname ( $file );
		if (! file_exists ( $dir )) {
			mkdir ( $dir, 0755, true );
		}
		if (! $this->overwrite && file_exists ( $file )) {
			$file = null;
		}
		return $file;
	}
	
	/**
	 * relative local file path
	 *
	 * @param unknown $url        	
	 * @return string
	 */
	private function getPath($url) {
		$ext = pathinfo ( $url, PATHINFO_EXTENSION );
		if (! in_array ( $ext, array (
				'htm',
				'html',
				'css',
				'js',
				'jpg',
				'jpeg',
				'gif',
				'png' 
		) )) {
			$url = rtrim ( $url, '/' ) . '/index.html';
		}
		$parse = parse_url ( $url );
		$port = '';
		if (isset ( $parse ['port'] )) {
			$port = '_' . $port;
		}
		return $parse ['scheme'] . '_' . $parse ['host'] . $port . $parse ['path'];
	}
}