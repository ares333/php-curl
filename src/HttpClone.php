<?php
namespace Ares333\Curlmulti;

use phpQuery;

/**
 * Automatic website copy tool whick keeping original structure.
 */
class HttpClone extends Toolkit
{

    // local file expire time
    public $expire = null;

    public $download = array(
        'pic' => true,
        'video' => false
    );

    public $blacklist = array();

    // zip,rar ...
    public $downloadExtension = array();

    // valid http code
    public $httpCode = array(
        200
    );

    protected $suffix = 'html';

    protected $index = 'index.html';

    protected $task = array();

    // absolute path of local dir
    protected $dir;

    // processed url
    protected $urlRequested = array();

    // windows system flag
    protected $isWin;

    /**
     *
     * @param string $dir
     */
    function __construct($dir)
    {
        parent::__construct();
        if (! is_dir($dir) || ! is_writable($dir)) {
            user_error('dir(' . $dir . ') is invalid');
        }
        $this->dir = $dir;
        $this->isWin = (0 === strpos(PHP_OS, 'WIN'));
    }

    /**
     *
     * @param string $url
     * @param int $depth
     * @return self
     */
    function add($url, $depth = null)
    {
        $url = $this->urlFormat($url);
        if (! isset($url)) {
            user_error('invalid url(' . $url . ')', E_USER_ERROR);
        }
        foreach (array_keys($this->task) as $v) {
            if (0 === strpos($url, $v) || 0 === strpos($v, $url)) {
                user_error("url($url) conflict with $v", E_USER_ERROR);
            }
        }
        $this->task[$url] = array(
            'depth' => $depth
        );
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Ares333\Curlmulti\Toolkit::formatUrl()
     */
    function urlFormat($url)
    {
        $url = parent::urlFormat($url);
        $parse = parse_url($url);
        if (! isset($parse['path'])) {
            $parse['path'] = '/';
        }
        return $this->buildUrl($parse);
    }

    /**
     * Start loop.
     */
    function start()
    {
        foreach ($this->blacklist as $k => $v) {
            $this->blacklist[$k] = $this->urlFormat($v);
        }
        foreach (array_keys($this->task) as $v) {
            if ($this->checkUrl($v)) {
                $this->getCurl()->add(
                    array(
                        'opt' => array(
                            CURLOPT_URL => $v
                        ),
                        'args' => array(
                            'file' => $this->url2file($v)
                        )
                    ),
                    array(
                        $this,
                        'onProcess'
                    ));
            }
        }
        $this->getCurl()->start();
        if (isset($this->getCurl()->onInfo)) {
            echo "\n";
        }
    }

    /**
     *
     * @param string $url
     * @param bool $isLocal
     * @return string
     */
    protected function url2src($url, $urlCurrent, $isLocal)
    {
        $url = $this->urlFormat($url);
        if (in_array($url, $this->blacklist)) {
            return '';
        }
        if ($isLocal) {
            $url = $this->url2uri($url, $urlCurrent);
        }
        return $url;
    }

    /**
     * Process response.
     *
     * @param array $r
     * @param mixed $args
     *
     */
    function onProcess($r, $args)
    {
        if (in_array($r['info']['http_code'], $this->httpCode)) {
            $urlDownload = array();
            $urlParse = array();
            if (isset($r['body']) &&
                 0 === strpos($r['info']['content_type'], 'text')) {
                $r['body'] = $this->htmlEncode($r['body']);
                $urlCurrent = $r['info']['url'];
                $pq = phpQuery::newDocumentHTML($r['body']);
                // link
                $list = $pq['link'];
                foreach ($list as $v) {
                    $v = pq($v);
                    $type = $v->attr('type');
                    if (! empty($type) && 'css' === substr($type, - 3)) {
                        $isCss = true;
                    } else {
                        $isCss = false;
                    }
                    $url = $this->uri2url($v->attr('href'), $urlCurrent);
                    $v->attr('href', $this->url2src($url, $urlCurrent, true));
                    $urlDownload[$url] = $isCss ? array(
                        'type' => 'css'
                    ) : array();
                }
                // script
                $script = $pq['script'];
                foreach ($script as $v) {
                    $v = pq($v);
                    if (null != $v->attr('src')) {
                        $url = $this->uri2url($v->attr('src'), $urlCurrent);
                        $v->attr('src', $this->url2src($url, $urlCurrent, true));
                        $urlDownload[$url] = array();
                    }
                }
                // pic
                $pic = $pq['img,image'];
                if ($this->download['pic']) {
                    foreach ($pic as $v) {
                        $v = pq($v);
                        $url = $this->uri2url($v->attr('src'), $urlCurrent);
                        $v->attr('src', $this->url2src($url, $urlCurrent, true));
                        $urlDownload[$url] = array();
                    }
                } else {
                    foreach ($pic as $v) {
                        $v = pq($v);
                        $v->attr('src',
                            $this->url2src(
                                $this->uri2url($v->attr('src'), $urlCurrent),
                                $urlCurrent, false));
                    }
                }
                // pic for video poster
                $pic = $pq['video[poster]'];
                if ($this->download['pic']) {
                    foreach ($pic as $v) {
                        $v = pq($v);
                        $url = $this->uri2url($v->attr('poster'), $urlCurrent);
                        $v->attr('poster',
                            $this->url2src($url, $urlCurrent, true));
                        $urlDownload[$url] = array();
                    }
                } else {
                    foreach ($pic as $v) {
                        $v = pq($v);
                        $v->attr('poster',
                            $this->url2src(
                                $this->uri2url($v->attr('src'), $urlCurrent),
                                $urlCurrent, false));
                    }
                }
                // video
                $video = $pq['video source'];
                if ($this->download['video']) {
                    foreach ($video as $v) {
                        $v = pq($v);
                        $url = $this->uri2url($v->attr('src'), $urlCurrent);
                        $v->attr('src', $this->url2src($url, $urlCurrent, true));
                        $urlDownload[$url] = array();
                    }
                } else {
                    foreach ($video as $v) {
                        $v = pq($v);
                        $v->attr('src',
                            $this->url2src(
                                $this->uri2url($v->attr('src'), $urlCurrent),
                                $urlCurrent, false));
                    }
                }
                // href
                $a = $pq['a[href]'];
                foreach ($a as $v) {
                    $v = pq($v);
                    $href = $v->attr('href');
                    if (empty($href) ||
                         strtolower(substr(ltrim($href), 0, 11)) == 'javascript:') {
                        continue;
                    }
                    $url = $this->uri2url($href, $urlCurrent);
                    if ($this->isProcess($url)) {
                        if (in_array(pathinfo($href, PATHINFO_EXTENSION),
                            $this->downloadExtension)) {
                            $urlDownload[$url] = array();
                        } else {
                            $urlParse[$url] = array();
                        }
                        $v->attr('href',
                            $this->url2src($url, $urlCurrent, true));
                    } else {
                        $v->attr('href',
                            $this->url2src($url, $urlCurrent, false));
                    }
                }
                $r['body'] = $pq->html();
                $path = $args['file'];
                if (isset($path)) {
                    if ($this->isWin) {
                        $path = mb_convert_encoding($path, 'gbk', 'utf-8');
                    }
                    file_put_contents($path, $r['body'], LOCK_EX);
                }
                phpQuery::unloadDocuments();
            } elseif ($args['isDownload']) {
                if ('css' == $args['type']) {
                    $content = file_get_contents($args['file']);
                    $uri = array();
                    // import
                    preg_match_all('/@import\s+url\s*\((.+)\);/iU', $content,
                        $matches);
                    if (! empty($matches[1])) {
                        $uri = array_merge($uri, $matches[1]);
                    }
                    // url in css
                    preg_match_all('/:\s*url\((\'|")?(.+?)\\1?\)/i', $content,
                        $matches);
                    if (! empty($matches[2])) {
                        $uri = array_merge($uri, $matches[2]);
                    }
                    foreach ($uri as $v) {
                        $urlDownload[$this->url2dir($r['info']['url']) . $v] = array(
                            'type' => 'css'
                        );
                    }
                }
            }
            // add
            foreach (array(
                $urlDownload,
                $urlParse
            ) as $k => $v) {
                foreach ($v as $k1 => $v1) {
                    $k1 = $this->urlFormat($k1);
                    if ($this->checkUrl($k1)) {
                        $file = $this->url2file($k1);
                        if (null == $file) {
                            continue;
                        }
                        $type = null;
                        if (isset($v1['type'])) {
                            $type = $v1['type'];
                        }
                        $opt = array(
                            CURLOPT_URL => $k1
                        );
                        $isDownload = $k === 0;
                        if ($isDownload) {
                            $opt[CURLOPT_HEADER] = false;
                            $opt[CURLOPT_FILE] = fopen($file, 'w');
                        }
                        $item = array(
                            'opt' => $opt,
                            'args' => array(
                                'file' => $file,
                                'type' => $type,
                                'isDownload' => $isDownload
                            )
                        );
                        $this->getCurl()->add($item,
                            array(
                                $this,
                                'onProcess'
                            ));
                    }
                }
            }
        } else {
            $this->onInfo(
                '(' . $r['info']['http_code'] . ') ' . $r['info']['url'] . "\n");
            return array(
                'cache' => array(
                    'enable' => false
                )
            );
        }
    }

    /**
     * Process or not.
     *
     * @param string $url
     */
    protected function isProcess($url)
    {
        $doProcess = false;
        foreach ($this->task as $k => $v) {
            if (0 === strpos($url, $k)) {
                if (isset($v['depth'])) {
                    $depth = $this->urlDepth($url, $k);
                    if (isset($depth) && $depth > $v['depth']) {
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
     * Calculate relative depth.
     *
     * @param string $url
     * @param string $urlBase
     */
    protected function urlDepth($url, $urlBase)
    {
        if ($this->isUrl($urlBase)) {
            if (0 === strpos($url, $urlBase)) {
                $path = ltrim(substr($url, strlen($urlBase)), '/');
                if (false !== $path) {
                    $depth = 0;
                    if (! empty($path)) {
                        $depth = count(explode('/', $path));
                    }
                    return $depth;
                }
            }
        }
    }

    /**
     *
     * @param string $url
     * @param string $urlCurrent
     * @return string
     */
    public function url2uri($url, $urlCurrent)
    {
        $path = parent::url2uri($url, $urlCurrent);
        $path = $this->fixPath($path);
        if (! isset($path)) {
            $dir2 = $this->url2dir($urlCurrent);
            $path1 = $this->getPath($url);
            $path2 = ltrim(parse_url($dir2, PHP_URL_PATH), '/');
            $arr2 = array();
            if (! empty($path2)) {
                $arr2 = explode('/', rtrim($path2, '/'));
            }
            $path = '../';
            foreach ($arr2 as $v) {
                $path .= '../';
            }
            $path .= $path1;
        }
        return $path;
    }

    /**
     * Calculate local absolute path.
     *
     * @param string $url
     * @return string
     */
    protected function url2file($url)
    {
        $file = $this->dir . '/' . $this->getPath($url);
        $dir = dirname($file);
        if ($this->isWin) {
            $dir = mb_convert_encoding($dir, 'gbk', 'utf-8');
        }
        if (! file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        if (file_exists($file)) {
            if (! isset($this->expire) ||
                 time() - filemtime($file) < $this->expire) {
                $file = null;
            }
        }
        return $file;
    }

    /**
     * Get relative local file path.
     *
     * @param string $url
     * @return string
     */
    protected function getPath($url)
    {
        $parse = parse_url($url);
        $parse['path'] = $this->fixPath($parse['path']);
        $port = '';
        if (isset($parse['port'])) {
            $port = '_' . $parse['port'];
        }
        $path = $parse['scheme'] . '_' . $parse['host'] . $port;
        $path .= $parse['path'] . $this->getQuery($url);
        // [?#] is for brower
        $invalid = array(
            '?',
            '#'
        );
        $invalidName = array();
        if ($this->isWin) {
            $invalid = array_merge($invalid,
                array(
                    '*',
                    ':',
                    '|',
                    '\\',
                    '<',
                    '>'
                ));
            $invalidName = array(
                "con",
                "aux",
                "nul",
                "prn",
                "com0",
                "com1",
                "com2",
                "com3",
                "com4",
                "com5",
                "com6",
                "com7",
                "com8",
                "com9",
                "lpt0",
                "lpt1",
                "lpt2",
                "lpt3",
                "lpt4",
                "lpt5",
                "lpt6",
                "lpt7",
                "lpt8",
                "lpt9"
            );
        }
        $invalidNameReplace = array_map(
            function ($v) {
                return '_' . $v;
            }, $invalidName);
        $path = str_replace($invalid, '-', $path);
        $path = str_replace($invalidName, $invalidNameReplace, $path);
        return $path;
    }

    /**
     * Calculate query.
     *
     * @param string $url
     * @return string
     */
    protected function getQuery($url)
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! empty($query)) {
            parse_str($query, $query);
            asort($query);
            $query = http_build_query($query);
            if (strlen($query) >= 250) {
                $query = md5($query);
            }
            $query = '？' . $query;
        }
        return $query;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Ares333\Curlmulti\Toolkit::isUrl()
     */
    function isUrl($url)
    {
        if (parent::isUrl($url)) {
            return true;
        }
        return 0 === strpos($url, '//');
    }

    /**
     * Prevent same url download twice.
     *
     * @param string $url
     */
    protected function checkUrl($url)
    {
        if (! $this->isUrl($url)) {
            return false;
        }
        if (in_array($url, $this->blacklist)) {
            return false;
        }
        $md5 = md5($url, true);
        if (in_array($md5, $this->urlRequested)) {
            return false;
        } else {
            $this->urlRequested[] = $md5;
            return true;
        }
    }

    /**
     * Fix uri and file path.
     *
     * @param string $path
     * @return string
     */
    protected function fixPath($path)
    {
        if (! isset($path)) {
            return;
        }
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (empty($ext)) {
            if (substr($path, - 1) === '/') {
                $path .= $this->index;
            } else {
                $path .= empty($this->suffix) ? '' : '.' . $this->suffix;
            }
        }
        return $path;
    }
}