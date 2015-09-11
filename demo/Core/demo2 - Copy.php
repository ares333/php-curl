<?php
include '../CurlMulti/Core.php';
include '../CurlMulti/My.php';
include '../phpQuery.php';
class Demo extends CurlMulti_Base {
	private $baseUrl = 'http://www.1ting.com';
	private $noCacheNum = 10;
	private $imgDir;
	function __construct($curl) {
		parent::__construct ( $curl );
		$cacheDir = __DIR__ . '/cache';
		$this->imgDir = __DIR__ . '/image';
		if (! is_dir ( $cacheDir ))
			mkdir ( $cacheDir );
		if (! is_dir ( $this->imgDir ))
			mkdir ( $this->imgDir );
		$this->getCurl ()->cache ['dir'] = $cacheDir;
		$this->getCurl ()->cache ['enalbe'] = true;
		$this->getCurl ()->maxThread = 12;
		$this->getCurl ()->opt [CURLOPT_CONNECTTIMEOUT] = 10;
		$this->getCurl ()->cbInfo = array (
				$this,
				'cbCurlInfo' 
		);
		$this->getCurl ()->maxThreadType ['img'] = 10;
	}
	/**
	 * start the loop here
	 */
	function fuck() {
		$this->getCurl ()->add ( array (
				'url' => $this->baseUrl . '/group/group0_1.html',
				'args' => array (
						// this argument can be passed straight forward
						'test1' => '123' 
				) 
		), array (
				$this,
				'cb1' 
		) )->add ( array (
				'url' => 'http://urlnotexists',
				// take effect only for current task
				'opt' => array (
						CURLOPT_CONNECTTIMEOUT => 1,
						CURLOPT_TIMEOUT => 1 
				) 
		), null, function ($error) {
			// task fail call back for current task
			echo "\n";
			print_r ( $error );
		} )->add ( array (
				// this will call CurlMulti_Core::cbFail
				'url' => 'http://urlnotexits',
				'opt' => array (
						CURLOPT_CONNECTTIMEOUT => 1,
						CURLOPT_TIMEOUT => 1 
				) 
		), null )->start ();
	}
	/**
	 * parse list
	 *
	 * @param unknown $r        	
	 * @param unknown $param        	
	 */
	function cb1($r, $param) {
		if (! $this->hasHttpError ( $r ['info'] )) {
			$html = phpQuery::newDocumentHTML ( $r ['content'] );
			$list = $html ['div.singerList:has(h3:contains(\'M\')) ul.allSinger li a'];
			foreach ( $list as $v ) {
				$v = pq ( $v );
				$artistName = $v->text ();
				echo "cb1:\t" . $artistName . "\n";
				$useCache = true;
				if (-- $this->noCacheNum > 0) {
					$useCache = false;
				}
				$this->getCurl ()->add ( array (
						'url' => $this->baseUrl . $v->attr ( 'href' ),
						'ctl' => array (
								'useCache' => $useCache 
						),
						'args' => array_merge ( $param, array (
								'artistName' => $artistName 
						) ) 
				), array (
						$this,
						'cb2' 
				) );
			}
			phpQuery::unloadDocuments ();
		}
	}
	/**
	 * add image download task, echo parsed content
	 *
	 * @param unknown $r        	
	 * @param unknown $param        	
	 */
	function cb2($r, $param) {
		if (! $this->hasHttpError ( $r ['info'] )) {
			$html = phpQuery::newDocumentHTML ( $r ['content'] );
			$list = $html ['#song-list td.songTitle a'];
			foreach ( $list as $v ) {
				$v = pq ( $v );
				echo "cb2:\t" . $v->text () . "\n";
			}
			$imgUrl = $html ['div.sidebar dl.singerInfo img']->attr ( 'src' );
			$imgFile = $this->imgDir . '/' . $param ['artistName'] . '.' . pathinfo ( $imgUrl, PATHINFO_EXTENSION );
			$this->getCurl ()->add ( array (
					'url' => $imgUrl,
					'file' => $imgFile,
					'ctl' => array (
							'type' => 'img' 
					),
					'args' => array_merge ( $param, array (
							'imgFile' => $imgFile 
					) ) 
			), array (
					$this,
					'cb3' 
			) );
			phpQuery::unloadDocuments ();
		}
	}
	/**
	 * image download finished callback and echo the argument seted in $this->start()
	 *
	 * @param unknown $r        	
	 * @param unknown $param        	
	 */
	function cb3($r, $param) {
		if (! $this->hasHttpError ( $r ['info'] )) {
			echo "$param[imgFile] download finished. test1=" . $param ['test1'] . "\n";
		}
	}
	function cbCurlInfo($info) {
		echo "\n";
		parent::cbCurlInfo ( $info );
		echo "\n";
	}
}
$demo = new Demo ( new CurlMulti_Core () );
$demo->fuck ();