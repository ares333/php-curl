<?php
namespace Ares333\Curl;

/**
 * Toolkit for Curl
 */
class Toolkit
{

    // Curl instance
    protected $_curl;

    function __construct(Curl $curl = null)
    {
        $this->_curl = $curl;
        if (! isset($this->_curl)) {
            $this->_curl = new Curl();
            $this->_curl->opt = array(
                CURLINFO_HEADER_OUT => true,
                CURLOPT_HEADER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_AUTOREFERER => true,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_MAXREDIRS => 5
            );
            // default fail callback
            $this->_curl->onFail = array(
                $this,
                'onFail'
            );
            // default info callback
            $this->_curl->onInfo = array(
                $this,
                'onInfo'
            );
        }
    }

    /**
     * Output curl error infomation
     *
     * @param array $error
     * @param mixed $args
     */
    function onFail($error, $args)
    {
        $msg = "Curl error ($error[errorCode])$error[errorMsg], url=" .
             $error['info']['url'];
        if ($this->_curl->onInfo == array(
            $this,
            'onInfo'
        )) {
            $this->onInfo($msg . "\n");
        } else {
            echo "\n$msg\n\n";
        }
    }

    /**
     *
     * Add delayed and formated output or output with running information.
     *
     * @param array|string $info
     *            array('all'=>array(),'running'=>array())
     * @param Curl $curl
     * @param bool $isLast
     *
     */
    function onInfo($info, $curl = null, $isLast = null)
    {
        static $meta = array(
            'downloadSpeed' => array(
                0,
                'SPD'
            ),
            'downloadSize' => array(
                0,
                'DWN'
            ),
            'finishNum' => array(
                0,
                'FNH'
            ),
            'cacheNum' => array(
                0,
                'CACHE'
            ),
            'taskRunningNum' => array(
                0,
                'RUN'
            ),
            'activeNum' => array(
                0,
                'ACTIVE'
            ),
            'taskPoolNum' => array(
                0,
                'POOL'
            ),
            'queueNum' => array(
                0,
                'QUEUE'
            ),
            'taskNum' => array(
                0,
                'TASK'
            ),
            'failNum' => array(
                0,
                'FAIL'
            )
        );
        static $isFirst = true;
        static $buffer = '';
        if (is_string($info)) {
            $buffer .= $info;
            return;
        }
        $all = $info['all'];
        $all['downloadSpeed'] = round($all['downloadSpeed'] / 1024) . 'KB';
        $all['downloadSize'] = round(
            ($all['headerSize'] + $all['bodySize']) / 1024 / 1024) . "MB";
        // clean
        foreach (array_keys($meta) as $v) {
            if (! array_key_exists($v, $all)) {
                unset($meta[$v]);
            }
        }
        $content = '';
        $lenPad = 2;
        $caption = '';
        foreach (array(
            'meta'
        ) as $name) {
            foreach ($$name as $k => $v) {
                if (! isset($all[$k])) {
                    continue;
                }
                if (mb_strlen($all[$k]) > $v[0]) {
                    $v[0] = mb_strlen($all[$k]);
                }
                if (PHP_OS == 'Linux') {
                    if (mb_strlen($v[1]) > $v[0]) {
                        $v[0] = mb_strlen($v[1]);
                    }
                    $caption .= sprintf('%-' . ($v[0] + $lenPad) . 's', $v[1]);
                    $content .= sprintf('%-' . ($v[0] + $lenPad) . 's',
                        $all[$k]);
                } else {
                    $format = '%-' . ($v[0] + strlen($v[1]) + 1 + $lenPad) . 's';
                    $content .= sprintf($format, $v[1] . ':' . $all[$k]);
                }
                ${$name}[$k] = $v;
            }
        }
        $str = '';
        if (PHP_OS == 'Linux') {
            if ($isFirst) {
                $str .= "\n";
                $isFirst = false;
            }
            $str .= "\33[A\r\33[K" . $caption . "\n\r\33[K" . rtrim($content);
        } else {
            $str .= "\r" . rtrim($content);
        }
        echo $str;
        if ($isLast) {
            echo "\n";
        }
        if ('' !== $buffer) {
            if ($isLast) {
                echo trim($buffer) . "\n";
            } else {
                echo "\n" . trim($buffer) . "\n\n";
            }
            $buffer = '';
        }
    }

    /**
     * Html encoding transform
     *
     * @param string $html
     * @param string $in
     *            detecte automaticly if not set
     * @param string $out
     *            default UTF-8
     * @param string $mode
     *            auto|iconv|mb_convert_encoding
     * @return string
     */
    function htmlEncode($html, $in = null, $out = null, $mode = 'auto')
    {
        $valid = array(
            'auto',
            'iconv',
            'mb_convert_encoding'
        );
        if (! isset($out)) {
            $out = 'UTF-8';
        }
        if (! in_array($mode, $valid)) {
            user_error('invalid mode, mode=' . $mode, E_USER_ERROR);
        }
        $if = function_exists('mb_convert_encoding');
        $if = $if && ($mode == 'auto' || $mode == 'mb_convert_encoding');
        if (function_exists('iconv') && ($mode == 'auto' || $mode == 'iconv')) {
            $func = 'iconv';
        } elseif ($if) {
            $func = 'mb_convert_encoding';
        } else {
            user_error('encode failed, php extension not found', E_USER_ERROR);
        }
        $pattern = '/(<meta[^>]*?charset=(["\']?))([a-z\d_\-]*)(\2[^>]*?>)/is';
        if (! isset($in)) {
            $n = preg_match($pattern, $html, $in);
            if ($n > 0) {
                $in = $in[3];
            } else {
                if (function_exists('mb_detect_encoding')) {
                    $in = mb_detect_encoding($html);
                } else {
                    $in = null;
                }
            }
        }
        if (isset($in)) {
            $old = error_reporting(error_reporting() & ~ E_NOTICE);
            $html = call_user_func($func, $in, $out . '//IGNORE', $html);
            error_reporting($old);
            $html = preg_replace($pattern, "\\1$out\\4", $html, 1);
        } else {
            user_error('source encoding is unknown', E_USER_ERROR);
        }
        return $html;
    }

    /**
     * content between start and end
     *
     * @param string $str
     * @param string $start
     * @param string $end
     * @param bool $greed
     * @return string
     */
    function between($str, $start, $end = null, $greed = true)
    {
        if (isset($start)) {
            $pos1 = strpos($str, $start);
        } else {
            $pos1 = 0;
        }
        if (isset($end)) {
            if ($greed) {
                $pos2 = strrpos($str, $end);
            } else {
                $pos2 = strpos($str, $end, $pos1);
            }
        } else {
            $pos2 = strlen($str);
        }
        if (false === $pos1 || false === $pos2 || $pos2 < $pos1) {
            return '';
        }
        $len = strlen($start);
        return substr($str, $pos1 + $len, $pos2 - $pos1 - $len);
    }

    /**
     *
     * @param string $url
     * @return boolean
     */
    function isUrl($url)
    {
        $url = ltrim($url);
        return in_array(substr($url, 0, 7),
            array(
                'http://',
                'https:/'
            ));
    }

    /**
     * Clean up and format
     *
     * @param string $url
     * @return string
     */
    function urlFormater($url)
    {
        if (! $this->isUrl($url)) {
            return;
        }
        $url = trim($url);
        $url = str_replace(' ', '+', $url);
        $parse = parse_url($url);
        strtolower($parse['scheme']);
        strtolower($parse['host']);
        return $this->buildUrl($parse);
    }

    /**
     *
     * @param array $parse
     * @return string
     */
    function buildUrl(array $parse)
    {
        $keys = array(
            'scheme',
            'host',
            'port',
            'user',
            'pass',
            'path',
            'query',
            'fragment'
        );
        foreach ($keys as $v) {
            if (! isset($parse[$v])) {
                $parse[$v] = '';
            }
        }
        if ('' !== $parse['scheme']) {
            $parse['scheme'] .= '://';
        }
        if ('' !== $parse['user']) {
            $parse['user'] .= ':';
            $parse['pass'] .= '@';
        }
        if ('' !== $parse['port']) {
            $parse['host'] .= ':';
        }
        if ('' !== $parse['query']) {
            $parse['path'] .= '?';
            // sort
            parse_str($parse['query'], $query);
            asort($query);
            $parse['query'] = http_build_query($query);
        }
        if ('' !== $parse['fragment']) {
            $parse['query'] .= '#';
        }
        $parse['path'] = preg_replace('/\/+/', '/', $parse['path']);
        return $parse['scheme'] . $parse['user'] . $parse['pass'] .
             $parse['host'] . $parse['port'] . $parse['path'] . $parse['query'] .
             $parse['fragment'];
    }

    /**
     *
     * @param string $uri
     * @param string $urlCurrent
     *            Should be final url which was redirected by 3xx http code.
     * @return string
     */
    function uri2url($uri, $urlCurrent)
    {
        if (empty($uri)) {
            return $urlCurrent;
        }
        if ($this->isUrl($uri)) {
            return $uri;
        }
        if (! $this->isUrl($urlCurrent)) {
            return;
        }
        // uri started with ?,#
        if (0 === strpos($uri, '#') || 0 === strpos($uri, '?')) {
            if (false !== ($pos = strpos($urlCurrent, '#'))) {
                $urlCurrent = substr($urlCurrent, 0, $pos);
            }
            if (false !== ($pos = strpos($urlCurrent, '?'))) {
                $urlCurrent = substr($urlCurrent, 0, $pos);
            }
            return $urlCurrent . $uri;
        }
        if (0 === strpos($uri, './')) {
            $uri = substr($uri, 2);
        }
        $urlDir = $this->url2dir($urlCurrent);
        if (0 === strpos($uri, '/')) {
            $path = parse_url($urlDir, PHP_URL_PATH);
            if (isset($path)) {
                $len = 0 - strlen($path);
            } else {
                $len = strlen($urlDir);
            }
            return substr($urlDir, 0, $len) . $uri;
        } else {
            return $urlDir . $uri;
        }
    }

    /**
     *
     * @param string $url
     * @param string $urlCurrent
     *            Should be final url which was redirected by 3xx http code.
     * @return string
     */
    function url2uri($url, $urlCurrent)
    {
        if (! $this->isUrl($url)) {
            return;
        }
        $urlDir = $this->url2dir($urlCurrent);
        $parse1 = parse_url($url);
        $parse2 = parse_url($urlDir);
        if (! array_key_exists('port', $parse1)) {
            $parse1['port'] = null;
        }
        if (! array_key_exists('port', $parse2)) {
            $parse2['port'] = null;
        }
        $eq = true;
        foreach (array(
            'scheme',
            'host',
            'port'
        ) as $v) {
            if (isset($parse1[$v]) && isset($parse2[$v])) {
                if ($parse1[$v] != $parse2[$v]) {
                    $eq = false;
                    break;
                }
            }
        }
        $path = null;
        if ($eq) {
            $len = strlen($urlDir) - strlen(parse_url($urlDir, PHP_URL_PATH));
            // relative path
            $path1 = substr($url, $len + 1);
            $path2 = substr($urlDir, $len + 1);
            $arr1 = explode('/', $path1);
            $arr2 = explode('/', $path2);
            foreach ($arr1 as $k => $v) {
                if (empty($v)) {
                    continue;
                }
                if (array_key_exists($k, $arr2) && $v == $arr2[$k]) {
                    unset($arr1[$k], $arr2[$k]);
                } else {
                    break;
                }
            }
            $path = '';
            foreach ($arr2 as $v) {
                if (empty($v)) {
                    continue;
                }
                $path .= '../';
            }
            $path .= implode('/', $arr1);
        }
        return $path;
    }

    /**
     *
     * @param string $url
     *            Should be final url which was redirected by 3xx http code.
     * @return string
     */
    function url2dir($url)
    {
        if (! $this->isUrl($url)) {
            return;
        }
        $parse = parse_url($url);
        $urlDir = $url;
        if (isset($parse['path'])) {
            if ('/' != substr($urlDir, - 1)) {
                $urlDir = dirname($urlDir) . '/';
            }
        } else {
            if ('/' != substr($urlDir, - 1)) {
                $urlDir .= '/';
            }
        }
        return $urlDir;
    }

    /**
     *
     * @return \Ares333\Curl\Curl
     */
    function getCurl()
    {
        return $this->_curl;
    }
}