<?php
/**
 * Website copy, keep original directory structure(be supported by sound reason)
 * phpQuery needed
 * 
 * @author admin@phpdr.net
 *
 */
class CurlMulti_My_Clone extends CurlMulti_My {
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
	function __construct($url, $dir) {
		parent::__construct ();
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
		if (! $this->hasHttpError ( $r ['info'] )) {
			if (isset ( $r ['content'] )) {
				$urlCurrent = $r ['info'] ['url'];
				$pq = phpQuery::newDocumentHTML ( $r ['content'] );
				$urlDownload = array ();
				// css
				$list = $pq ['link[type$=css]'];
				foreach ( $list as $v ) {
					$v = pq ( $v );
					$url = $this->uri2url ( $v->attr ( 'href' ), $urlCurrent );
					$v->attr ( 'href', $this->url2uri ( $url, $urlCurrent ) );
					$urlDownload [] = $url;
				}
				// script
				$script = $pq ['script[type$=script]'];
				foreach ( $script as $v ) {
					$v = pq ( $v );
					$url = $this->uri2url ( $v->attr ( 'src' ), $urlCurrent );
					$v->attr ( 'src', $this->url2uri ( $url, $urlCurrent ) );
					$urlDownload [] = $url;
				}
				// pic
				$pic = $pq ['img'];
				if ($this->downloadPic) {
					foreach ( $pic as $v ) {
						$v = pq ( $v );
						$url = $this->uri2url ( $v->attr ( 'src' ), $urlCurrent );
						$v->attr ( 'src', $this->url2uri ( $url, $urlCurrent ) );
						$urlDownload [] = $url;
					}
				} else {
					foreach ( $pic as $v ) {
						$v = pq ( $v );
						$v->attr ( 'src', $this->uri2url ( $v->attr ( 'src' ), $urlCurrent ) );
					}
				}
				// html
				$a = $pq ['a'];
				$urlHtml = array ();
				foreach ( $a as $v ) {
					$v = pq ( $v );
					$url = $this->uri2url ( $v->attr ( 'href' ), $urlCurrent );
					if (0 === strpos ( $url, $this->urlDir ( $urlCurrent ) )) {
						$v->attr ( 'href', $this->url2uri ( $url, $urlCurrent ) );
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
							if (null == $file && $v == 'urlDownload') {
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
				phpQuery::unloadDocuments ();
			}
		}
	}
	function url2uri($url, $urlCurrent) {
		$path = parent::url2uri ( $url, $urlCurrent );
		if (! isset ( $path )) {
			$urlDir = $this->urlDir ( $urlCurrent );
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
		$parse = parse_url ( $url );
		$ext = pathinfo ( $parse ['path'], PATHINFO_EXTENSION );
		if (empty ( $ext )) {
			$parse ['path'] = rtrim ( $parse ['path'], '/' ) . '/index.html';
		}
		$port = '';
		if (isset ( $parse ['port'] )) {
			$port = '_' . $port;
		}
		return $parse ['scheme'] . '_' . $parse ['host'] . $port . $parse ['path'];
	}
}