<?php

namespace Ares333\CurlMulti;

use phpQuery;

/**
 * Website copy, keep original directory structure(be supported by sounded reason)
 * phpQuery needed
 *
 * @author 275244351@qq.com
 *
 */
class AutoClone extends Base {
	// overwrite local file
	public $overwrite = false;
	// if download resource
	public $download = array (
			'pic' => array (
					'enable' => true
			),
			'zip' => array (
					'enable' => true,
					'withPrefix' => false
			)
	);
	// max file name length
	public $fileNameLen = 250;
	public $logError = true;
	private $errorLog;
	private $startTime;
	// init url
	private $url;
	// absolute local dir
	private $dir;
	// processed url
	private $urlAdded = array ();
	// all site
	private $site = array ();
	// windows system flag
	private $isWin;

	/**
	 *
	 * @param Core $curlmulti
	 * @param string $url
	 * @param string $dir
	 */
	function __construct($url, $dir) {
		parent::__construct ();
		$this->startTime = time () - 1;
		if (is_array ( $url )) {
			$urlNew = array ();
			foreach ( $url as $k => $v ) {
				if (is_array ( $v )) {
					foreach ( $v as $k1 => $v1 ) {
						$path = '';
						if ('/' != $v1) {
							$path = '/' . ltrim ( $k1, '/' );
						}
						$urlNew [rtrim ( $k, '/' ) . $path] = $v1;
					}
				} elseif (is_string ( $v )) {
					$urlNew [] = $v;
				} else {
					user_error ( 'url is invalid', E_USER_ERROR );
				}
			}
			$url = $urlNew;
		} else {
			user_error ( 'url is invalid', E_USER_ERROR );
		}
		foreach ( $url as $k => $v ) {
			if (! $this->isUrl ( $k )) {
				user_error ( 'url is invalid, url=' . $k, E_USER_ERROR );
			}
		}
		if (! is_dir ( $dir )) {
			user_error ( 'dir not found, dir=' . $dir, E_USER_ERROR );
		}
		$this->url = $url;
		$this->dir = $dir;
		$this->isWin = (0 === strpos ( PHP_OS, 'WIN' ));
	}

	/**
	 * start clone
	 */
	function start() {
		if (! empty ( $this->getCurl ()->cache ['dir'] )) {
			$this->errorLog = $this->getCurl ()->cache ['dir'] . '/autoCloneError.log';
		} else {
			$this->errorLog = './autoCloneError.log';
		}
		foreach ( $this->url as $k => $v ) {
			if ('/' != substr ( $k, - 1 )) {
				$this->getCurl ()->add ( array (
						'url' => $k,
						'opt' => array (
								CURLOPT_NOBODY => true
						)
				), function ($r) use($k, $v) {
					if ($k != $r ['info'] ['url']) {
						$this->url [$r ['info'] ['url']] = $v;
						unset ( $this->url [$k] );
					}
				} );
			}
		}
		$this->getCurl ()->start ();
		if (isset ( $this->getCurl ()->cbInfo ) && PHP_OS == 'Linux') {
			echo "\n";
		}
		foreach ( $this->url as $k => $v ) {
			$this->getCurl ()->add ( array (
					'url' => $k,
					'args' => array (
							'url' => $k,
							'file' => $this->url2file ( $k )
					)
			), array (
					$this,
					'cbProcess'
			) );
			$this->urlAdd ( $k );
		}
		$this->getCurl ()->start ();
	}
	/**
	 * download and html callback
	 *
	 * @param array $r
	 * @param mixed $args
	 *
	 */
	function cbProcess($r, $args) {
		if (200 == $r ['info'] ['http_code']) {
			$urlDownload = array ();
			$urlParse = array ();
			if (isset ( $r ['content'] ) && 0 === strpos ( $r ['info'] ['content_type'], 'text' )) {
				$urlCurrent = $args ['url'];
				$pq = phpQuery::newDocumentHTML ( $r ['content'] );
				// css
				$list = $pq ['link[type$=css]'];
				foreach ( $list as $v ) {
					$v = pq ( $v );
					$url = $this->uri2url ( $v->attr ( 'href' ), $urlCurrent );
					$v->attr ( 'href', $this->url2uriClone ( $url, $urlCurrent ) );
					$urlDownload [$url] = array (
							'type' => 'css'
					);
				}
				// script
				$script = $pq ['script[type$=script]'];
				foreach ( $script as $v ) {
					$v = pq ( $v );
					if (null != $v->attr ( 'src' )) {
						$url = $this->uri2url ( $v->attr ( 'src' ), $urlCurrent );
						$v->attr ( 'src', $this->url2uriClone ( $url, $urlCurrent ) );
						$urlDownload [$url] = array ();
					}
				}
				// pic
				$pic = $pq ['img'];
				if ($this->download ['pic'] ['enable']) {
					foreach ( $pic as $v ) {
						$v = pq ( $v );
						$url = $this->uri2url ( $v->attr ( 'src' ), $urlCurrent );
						$v->attr ( 'src', $this->url2uriClone ( $url, $urlCurrent ) );
						$urlDownload [$url] = array ();
					}
				} else {
					foreach ( $pic as $v ) {
						$v = pq ( $v );
						$v->attr ( 'src', $this->uri2url ( $v->attr ( 'src' ), $urlCurrent ) );
					}
				}
				// link xml
				$list = $pq ['link[type$=xml]'];
				foreach ( $list as $v ) {
					$v = pq ( $v );
					$url = $this->uri2url ( $v->attr ( 'href' ), $urlCurrent );
					if ($this->isProcess ( $url )) {
						$v->attr ( 'href', $this->url2uriClone ( $url, $urlCurrent ) );
						$urlDownload [$url] = array ();
					}
				}
				// href
				$a = $pq ['a[href]'];
				foreach ( $a as $v ) {
					$v = pq ( $v );
					$href = $v->attr ( 'href' );
					if (strtolower ( substr ( ltrim ( $href ), 0, 11 ) ) == 'javascript:') {
						continue;
					}
					$url = $this->uri2url ( $href, $urlCurrent );
					if ($this->download ['zip'] ['enable'] && '.zip' == substr ( $href, - 4 )) {
						if ($this->download ['zip'] ['withPrefix']) {
							$isProcess = $this->isProcess ( $url );
						} else {
							$isProcess = true;
						}
						if ($isProcess) {
							$urlDownload [$url] = array ();
						}
					} else {
						$isProcess = $this->isProcess ( $url );
						if ($isProcess) {
							$urlParse [$url] = array ();
						}
					}
					if ($isProcess) {
						$v->attr ( 'href', $this->url2uriClone ( $url, $urlCurrent ) );
					} else {
						$v->attr ( 'href', $url );
					}
				}
				$r ['content'] = $pq->html ();
				$path = $args ['file'];
				if (isset ( $path )) {
					if ($this->isWin) {
						$path = mb_convert_encoding ( $path, 'gbk', 'utf-8' );
					}
					if (false === file_put_contents ( $path, $r ['content'], LOCK_EX )) {
						user_error ( 'write file failed, file=' . $path, E_USER_WARNING );
					}
				}
				phpQuery::unloadDocuments ();
			} elseif ($args ['isDownload']) {
				if ('css' == $args ['type']) {
					$content = file_get_contents ( $args ['file'] );
					$uri = array ();
					// import
					preg_match_all ( '/@import\s+url\s*\((.+)\);/iU', $content, $matches );
					if (! empty ( $matches [1] )) {
						$uri = array_merge ( $uri, $matches [1] );
					}
					// url in css
					preg_match_all ( '/:\s*url\((\'|")?(.+?)\\1?\)/i', $content, $matches );
					if (! empty ( $matches [2] )) {
						$uri = array_merge ( $uri, $matches [2] );
					}
					foreach ( $uri as $v ) {
						$urlDownload [$this->urlDir ( $r ['info'] ['url'] ) . $v] = array (
								'type' => 'css'
						);
					}
				}
			}
			// add
			foreach ( array (
					'urlDownload',
					'urlParse'
			) as $v ) {
				foreach ( $$v as $k1 => $v1 ) {
					if (! $this->urlAdd ( $k1, true )) {
						$file = $this->url2file ( $k1 );
						if (null == $file) {
							continue;
						}
						$type = null;
						if (isset ( $v1 ['type'] )) {
							$type = $v1 ['type'];
						}
						$item = array (
								'url' => $k1,
								'file' => $file,
								'args' => array (
										'url' => $k1,
										'file' => $file,
										'type' => $type,
										'isDownload' => $v == 'urlDownload'
								)
						);
						if ($v == 'urlParse') {
							unset ( $item ['file'] );
						}
						$this->getCurl ()->add ( $item, array (
								$this,
								'cbProcess'
						) );
						$this->urlAdd ( $k1 );
					}
				}
			}
		} else {
			return array (
					'error' => 'http error ' . $r ['info'] ['http_code'],
					'cache' => array (
							'enable' => false
					)
			);
		}
	}

	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \Ares333\CurlMulti\Base::cbCurlFail()
	 */
	function cbCurlFail($error, $args) {
		if ($this->logError) {
			$err = $error ['error'];
			file_put_contents ( $this->errorLog, "Curl error $err[0]: $err[1], url=" . $error ['info'] ['url'] . "\n", FILE_APPEND );
		} else {
			parent::cbCurlFail ( $error, $args );
		}
	}

	/**
	 * is needed to process
	 *
	 * @param unknown $url
	 */
	private function isProcess($url) {
		$doProcess = false;
		foreach ( $this->url as $k1 => $v1 ) {
			if (0 === strpos ( $url, $k1 ) || $url . '/' == $k1) {
				if (! empty ( $v1 ['depth'] )) {
					$temp = $this->urlDepth ( $url, $k1 );
					if (isset ( $temp ) && $temp > $v1 ['depth']) {
						continue;
					}
				}
				$doProcess = true;
				break;
			}
		}
		return $doProcess;
	}

	/**
	 * calculate relative depth
	 *
	 * @param string $url
	 * @param string $urlBase
	 */
	private function urlDepth($url, $urlBase) {
		if ($this->isUrl ( $url ) && $this->isUrl ( $urlBase )) {
			if (0 === strpos ( $url, $urlBase )) {
				$path = ltrim ( substr ( $url, strlen ( $urlBase ) ), '/' );
				if (false !== $path) {
					$depth = 0;
					if (! empty ( $path )) {
						$depth = count ( explode ( '/', $path ) );
					}
					return $depth;
				}
			}
		}
	}

	/**
	 * url2uri for this class
	 *
	 * @param string $url
	 * @param string $urlCurrent
	 * @return string
	 */
	private function url2uriClone($url, $urlCurrent) {
		$url = $this->rebuildUrl($url);
		$path = $this->url2uri ( $url, $urlCurrent );
		if (! isset ( $path )) {
			$dir2 = $this->urlDir ( $urlCurrent );
			$path1 = $this->getPath ( $url );
			$path2 = ltrim ( parse_url ( $dir2, PHP_URL_PATH ), '/' );
			$arr2 = array ();
			if (! empty ( $path2 )) {
				$arr2 = explode ( '/', rtrim ( $path2, '/' ) );
			}
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
	 * @param string $url
	 * @return string
	 */
	private function url2file($url) {
		$url = $this->removeFragment($url);	
		$url = $this->rebuildUrl($url);	
		$file = $this->dir . '/' . $this->getPath ( $url );
		$dir = dirname ( $file );
		if ($this->isWin) {
			$dir = mb_convert_encoding ( $dir, 'gbk', 'utf-8' );
		}
		if (! file_exists ( $dir )) {
			mkdir ( $dir, 0755, true );
		}
		if (file_exists ( $file )) {
			$mtime = filemtime ( $file );
			if ($mtime > $this->startTime || ! $this->overwrite) {
				$file = null;
			}
		}
		return $file;
	}

	/**
	 * relative local file path
	 *
	 * @param string $url
	 * @return string
	 */
	private function getPath($url) {
		$parse = parse_url ($url);
		$port = '';
		if (isset ( $parse ['port'] )) {
			$port = '_' . $parse ['port'];
		}
		$path = $parse ['scheme'] . '_' . $parse ['host'] . $port . $parse ['path'];
		return $path;
	}


	/**
	 * add processed url or check
	 *
	 * @param string $url
	 * @param bool $check
	 */
	private function urlAdd($url, $check = false) {
		$url = $this->removeFragment($url);		
		$md5 = md5 ( $url );
		$level1 = substr ( $md5, 0, 3 );
		$level2 = substr ( $md5, 3, 3 );
		if ($check) {
			return ! empty ( $this->urlAdded [$level1] [$level2] ) && in_array ( $url, $this->urlAdded [$level1] [$level2] );
		} else {
			if (! array_key_exists ( $level1, $this->urlAdded )) {
				$this->urlAdded [$level1] = array (
						$level2 => array (
								$url
						)
				);
			} elseif (! array_key_exists ( $level2, $this->urlAdded [$level1] )) {
				$this->urlAdded [$level1] [$level2] = array (
						$url
				);
			} elseif (! in_array ( $url, $this->urlAdded [$level1] [$level2] )) {
				$this->urlAdded [$level1] [$level2] [] = $url;
			}
		}
	}
	
	/**
	 * trans query as a part of file name
	 *
	 */	
	private function rebuildUrl($url){
		$pos = strrpos ( $url, '#' );
		if (false !== $pos) {
			$frag = substr ( $url, $pos);		
			$url = substr ( $url, 0, $pos );				
		}
		$pos = strrpos ( $url, '?' );
		if (false !== $pos) {
			$query = substr ( $url, $pos + 1);		
			$url = substr ( $url, 0, $pos );									
		}
		$ext = pathinfo ( parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
		if (empty ( $ext )) {
			$url = rtrim ($url, '/' ) . '/index.html';
			$ext = 'html';
		}	
		if($query){
			if (strlen ($query) + strlen (pathinfo($url, PATHINFO_BASENAME)) + 1 >= $this->fileNameLen) {
				$query = md5 ( $query );
			}
			$url = preg_replace("/\.".$ext."$/", "_".$query.".".$ext, $url);
		}
		return $url.$frag;		
	}
	/**
	 * remove fragment of url
	 */	
	function removeFragment($url){
		$pos = strrpos ( $url, '#' );
		if (false !== $pos) {
			$url = substr ( $url, 0, $pos );	
		}
		return $url;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 *
	 * sort qeury keys and trim url
	 * 
	 * @see \Ares333\CurlMulti\Base::uri2url()
	 */
	function uri2url($uri, $urlCurrent){
		$url = parent::uri2url($uri, $urlCurrent);
		//sort query
		$pos = strrpos ( $url, '?' );
		if (false !== $pos) {
			parse_str(parse_url($url, PHP_URL_QUERY ), $query);
			ksort($query);
			$url = substr ( $url, 0, $pos ).'?'.http_build_query ( $query );
			$frag = parse_url($url, PHP_URL_FRAGMENT);
			if(! empty($frag)){
				$url .= "#".$frag;
			}
		}		
		return trim($url);
	}
}