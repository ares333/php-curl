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

    public $downloadPic = true;

    // zip,rar ...
    public $downloadExtension = array();

    // valid http code
    public $validHttpCode = array(
        200
    );

    public $defaultSuffix = 'html';

    private $dumpFile = null;

    private $task = array();

    // absolute path of local dir
    private $dir;

    // processed url
    private $urlRequested = array();

    // windows system flag
    private $isWin;

    /**
     *
     * @param string $dir
     * @param string $dumpFile
     */
    function __construct($dir, $dumpFile = null)
    {
        if (! is_dir($dir) || ! is_writable($dir)) {
            user_error('dir(' . $dir . ') is invalid');
        }
        $this->dir = $dir;
        parent::__construct($dumpFile);
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
        $url = $this->formatUrl($url);
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
    function formatUrl($url)
    {
        $url = parent::formatUrl($url);
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
        foreach (array_keys($this->task) as $v) {
            if ($this->checkUrl($v)) {
                $this->getCurl()->add(
                    array(
                        'opt' => array(
                            CURLOPT_URL => $v
                        ),
                        'args' => array(
                            'url' => $v,
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
     * Process response.
     *
     * @param array $r
     * @param mixed $args
     *
     */
    function onProcess($r, $args)
    {
        if (in_array($r['info']['http_code'], $this->validHttpCode)) {
            $urlDownload = array();
            $urlParse = array();
            if (isset($r['body']) &&
                 0 === strpos($r['info']['content_type'], 'text')) {
                $r['body'] = $this->htmlEncode($r['body']);
                $urlCurrent = $args['url'];
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
                    $v->attr('href', $this->url2uriClone($url, $urlCurrent));
                    $urlDownload[$url] = $isCss ? array(
                        'type' => 'css'
                    ) : array();
                }
                // script
                $script = $pq['script[type$=script]'];
                foreach ($script as $v) {
                    $v = pq($v);
                    if (null != $v->attr('src')) {
                        $url = $this->uri2url($v->attr('src'), $urlCurrent);
                        $v->attr('src', $this->url2uriClone($url, $urlCurrent));
                        $urlDownload[$url] = array();
                    }
                }
                // pic
                $pic = $pq['img,image'];
                if ($this->downloadPic) {
                    foreach ($pic as $v) {
                        $v = pq($v);
                        $url = $this->uri2url($v->attr('src'), $urlCurrent);
                        $v->attr('src', $this->url2uriClone($url, $urlCurrent));
                        $urlDownload[$url] = array();
                    }
                } else {
                    foreach ($pic as $v) {
                        $v = pq($v);
                        $v->attr('src',
                            $this->uri2url($v->attr('src'), $urlCurrent));
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
                    $ext = pathinfo($href, PATHINFO_EXTENSION);
                    if ($this->isProcess($url)) {
                        if (in_array($ext, $this->downloadExtension)) {
                            $urlDownload[$url] = array();
                        } else {
                            $urlParse[$url] = array();
                        }
                        $v->attr('href', $this->url2uriClone($url, $urlCurrent));
                    } else {
                        $v->attr('href', $url);
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
                        $urlDownload[$this->urlDir($r['info']['url']) . $v] = array(
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
                    $k1 = $this->formatUrl($k1);
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
                                'url' => $k1,
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
    private function isProcess($url)
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
    private function urlDepth($url, $urlBase)
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
    private function url2uriClone($url, $urlCurrent)
    {
        $path = $this->url2uri($url, $urlCurrent);
        $path = $this->fixPath($path);
        if (! isset($path)) {
            $dir2 = $this->urlDir($urlCurrent);
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
    private function url2file($url)
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
    private function getPath($url)
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
    private function getQuery($url)
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
    private function checkUrl($url)
    {
        if (! $this->isUrl($url)) {
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
    private function fixPath($path)
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (empty($ext)) {
            if (substr($path, - 1) === '/') {
                $path .= 'index.html';
            } else {
                $path .= empty($this->defaultSuffix) ? '' : '.html';
            }
        }
        return $path;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Ares333\Curlmulti\Toolkit::getSleepExclude()
     */
    protected function getSleepExclude()
    {
        return array_merge(parent::getSleepExclude(),
            array(
                'task',
                'dumpFile'
            ));
    }
}