<?php
namespace Ares333\Curl;

/**
 * Toolkit for Curl
 */
class Toolkit
{

    // Curl instance
    protected $_curl;

    function setCurl(Curl $curl = null)
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
        $msg = "Curl error ($error[errorCode]). $error[errorMsg], url=" . $error['info']['url'];
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
        $all['downloadSize'] = round(($all['headerSize'] + $all['bodySize']) / 1024 / 1024) . "MB";
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
                    $content .= sprintf('%-' . ($v[0] + $lenPad) . 's', $all[$k]);
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
        return in_array(substr($url, 0, 7), array(
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
    function formatUrl($url)
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
            $query = [];
            parse_str($parse['query'], $query);
            asort($query);
            $parse['query'] = http_build_query($query);
        }
        if ('' !== $parse['fragment']) {
            $parse['query'] .= '#';
        }
        $parse['path'] = preg_replace('/\/+/', '/', $parse['path']);
        return $parse['scheme'] . $parse['user'] . $parse['pass'] . $parse['host'] . $parse['port'] . $parse['path'] .
            $parse['query'] . $parse['fragment'];
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
     * Combine a base URL and a relative URL to produce a new
     * absolute URL.
     * The base URL is often the URL of a page,
     * and the relative URL is a URL embedded on that page.
     *
     * This function implements the "absolutize" algorithm from
     * the RFC3986 specification for URLs.
     *
     * This function supports multi-byte characters with the UTF-8 encoding,
     * per the URL specification.
     *
     * Parameters:
     * baseUrl the absolute base URL.
     *
     * url the relative URL to convert.
     *
     * Return values:
     * An absolute URL that combines parts of the base and relative
     * URLs, or FALSE if the base URL is not absolute or if either
     * URL cannot be parsed.
     */
    function url2absolute($url, $urlCurrent)
    {
        // If relative URL has a scheme, clean path and return.
        $r = $this->splitUrl($url);
        if ($r === FALSE)
            return FALSE;
        if (! empty($r['scheme'])) {
            if (! empty($r['path']) && $r['path'][0] == '/')
                $r['path'] = $this->urlRemoveDotSegments($r['path']);
            return $this->joinUrl($r);
        }

        // Make sure the base URL is absolute.
        $b = $this->splitUrl($urlCurrent);
        if ($b === FALSE || empty($b['scheme']) || empty($b['host']))
            return FALSE;
        $r['scheme'] = $b['scheme'];

        // If relative URL has an authority, clean path and return.
        if (isset($r['host'])) {
            if (! empty($r['path']))
                $r['path'] = $this->urlRemoveDotSegments($r['path']);
            return $this->joinUrl($r);
        }
        unset($r['port']);
        unset($r['user']);
        unset($r['pass']);

        // Copy base authority.
        $r['host'] = $b['host'];
        if (isset($b['port']))
            $r['port'] = $b['port'];
        if (isset($b['user']))
            $r['user'] = $b['user'];
        if (isset($b['pass']))
            $r['pass'] = $b['pass'];

        // If relative URL has no path, use base path
        if (empty($r['path'])) {
            if (! empty($b['path']))
                $r['path'] = $b['path'];
            if (! isset($r['query']) && isset($b['query']))
                $r['query'] = $b['query'];
            return $this->joinUrl($r);
        }

        // If relative URL path doesn't start with /, merge with base path
        if ($r['path'][0] != '/') {
            $base = mb_strrchr($b['path'], '/', TRUE, 'UTF-8');
            if ($base === FALSE)
                $base = '';
            $r['path'] = $base . '/' . $r['path'];
        }
        $r['path'] = $this->urlRemoveDotSegments($r['path']);
        return $this->joinUrl($r);
    }

    /**
     * Filter out "." and ".." segments from a URL's path and return
     * the result.
     *
     * This function implements the "remove_dot_segments" algorithm from
     * the RFC3986 specification for URLs.
     *
     * This function supports multi-byte characters with the UTF-8 encoding,
     * per the URL specification.
     *
     * Parameters:
     * path the path to filter
     *
     * Return values:
     * The filtered path with "." and ".." removed.
     */
    function urlRemoveDotSegments($path)
    {
        // multi-byte character explode
        $inSegs = preg_split('!/!u', $path);
        $outSegs = array();
        foreach ($inSegs as $seg) {
            if ($seg == '' || $seg == '.')
                continue;
            if ($seg == '..')
                array_pop($outSegs);
            else
                array_push($outSegs, $seg);
        }
        $outPath = implode('/', $outSegs);
        if ($path[0] == '/')
            $outPath = '/' . $outPath;
        // compare last multi-byte character against '/'
        if ($outPath != '/' && (mb_strlen($path) - 1) == mb_strrpos($path, '/', 'UTF-8'))
            $outPath .= '/';
        return $outPath;
    }

    /**
     * This function parses an absolute or relative URL and splits it
     * into individual components.
     *
     * RFC3986 specifies the components of a Uniform Resource Identifier (URI).
     * A portion of the ABNFs are repeated here:
     *
     * URI-reference = URI
     * / relative-ref
     *
     * URI = scheme ":" hier-part [ "?" query ] [ "#" fragment ]
     *
     * relative-ref = relative-part [ "?" query ] [ "#" fragment ]
     *
     * hier-part = "//" authority path-abempty
     * / path-absolute
     * / path-rootless
     * / path-empty
     *
     * relative-part = "//" authority path-abempty
     * / path-absolute
     * / path-noscheme
     * / path-empty
     *
     * authority = [ userinfo "@" ] host [ ":" port ]
     *
     * So, a URL has the following major components:
     *
     * scheme
     * The name of a method used to interpret the rest of
     * the URL. Examples: "http", "https", "mailto", "file'.
     *
     * authority
     * The name of the authority governing the URL's name
     * space. Examples: "example.com", "user@example.com",
     * "example.com:80", "user:password@example.com:80".
     *
     * The authority may include a host name, port number,
     * user name, and password.
     *
     * The host may be a name, an IPv4 numeric address, or
     * an IPv6 numeric address.
     *
     * path
     * The hierarchical path to the URL's resource.
     * Examples: "/index.htm", "/scripts/page.php".
     *
     * query
     * The data for a query. Examples: "?search=google.com".
     *
     * fragment
     * The name of a secondary resource relative to that named
     * by the path. Examples: "#section1", "#header".
     *
     * An "absolute" URL must include a scheme and path. The authority, query,
     * and fragment components are optional.
     *
     * A "relative" URL does not include a scheme and must include a path. The
     * authority, query, and fragment components are optional.
     *
     * This function splits the $url argument into the following components
     * and returns them in an associative array. Keys to that array include:
     *
     * "scheme" The scheme, such as "http".
     * "host" The host name, IPv4, or IPv6 address.
     * "port" The port number.
     * "user" The user name.
     * "pass" The user password.
     * "path" The path, such as a file path for "http".
     * "query" The query.
     * "fragment" The fragment.
     *
     * One or more of these may not be present, depending upon the URL.
     *
     * Optionally, the "user", "pass", "host" (if a name, not an IP address),
     * "path", "query", and "fragment" may have percent-encoded characters
     * decoded. The "scheme" and "port" cannot include percent-encoded
     * characters and are never decoded. Decoding occurs after the URL has
     * been parsed.
     *
     * Parameters:
     * url the URL to parse.
     *
     * decode an optional boolean flag selecting whether
     * to decode percent encoding or not. Default = TRUE.
     *
     * Return values:
     * the associative array of URL parts, or FALSE if the URL is
     * too malformed to recognize any parts.
     */
    protected function splitUrl($url, $decode = TRUE)
    {
        // Character sets from RFC3986.
        $xunressub = 'a-zA-Z\d\-._~\!$&\'()*+,;=';
        $xpchar = $xunressub . ':@%';

        // Scheme from RFC3986.
        $xscheme = '([a-zA-Z][a-zA-Z\d+-.]*)';

        // User info (user + password) from RFC3986.
        $xuserinfo = '(([' . $xunressub . '%]*)' . '(:([' . $xunressub . ':%]*))?)';

        // IPv4 from RFC3986 (without digit constraints).
        $xipv4 = '(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})';

        // IPv6 from RFC2732 (without digit and grouping constraints).
        $xipv6 = '(\[([a-fA-F\d.:]+)\])';

        // Host name from RFC1035. Technically, must start with a letter.
        // Relax that restriction to better parse URL structure, then
        // leave host name validation to application.
        $xhost_name = '([a-zA-Z\d-.%]+)';

        // Authority from RFC3986. Skip IP future.
        $xhost = '(' . $xhost_name . '|' . $xipv4 . '|' . $xipv6 . ')';
        $xport = '(\d*)';
        $xauthority = '((' . $xuserinfo . '@)?' . $xhost . '?(:' . $xport . ')?)';

        // Path from RFC3986. Blend absolute & relative for efficiency.
        $xslash_seg = '(/[' . $xpchar . ']*)';
        $xpath_authabs = '((//' . $xauthority . ')((/[' . $xpchar . ']*)*))';
        $xpath_rel = '([' . $xpchar . ']+' . $xslash_seg . '*)';
        $xpath_abs = '(/(' . $xpath_rel . ')?)';
        $xapath = '(' . $xpath_authabs . '|' . $xpath_abs . '|' . $xpath_rel . ')';

        // Query and fragment from RFC3986.
        $xqueryfrag = '([' . $xpchar . '/?' . ']*)';

        // URL.
        $xurl = '^(' . $xscheme . ':)?' . $xapath . '?' . '(\?' . $xqueryfrag . ')?(#' . $xqueryfrag . ')?$';

        // Split the URL into components.
        $m = [];
        if (! preg_match('!' . $xurl . '!', $url, $m))
            return FALSE;

        $parts = [];
        if (! empty($m[2]))
            $parts['scheme'] = strtolower($m[2]);

        if (! empty($m[7])) {
            if (isset($m[9]))
                $parts['user'] = $m[9];
            else
                $parts['user'] = '';
        }
        if (! empty($m[10]))
            $parts['pass'] = $m[11];

        if (! empty($m[13]))
            $h = $parts['host'] = $m[13];
        else if (! empty($m[14]))
            $parts['host'] = $m[14];
        else if (! empty($m[16]))
            $parts['host'] = $m[16];
        else if (! empty($m[5]))
            $parts['host'] = '';
        if (! empty($m[17]))
            $parts['port'] = $m[18];

        if (! empty($m[19]))
            $parts['path'] = $m[19];
        else if (! empty($m[21]))
            $parts['path'] = $m[21];
        else if (! empty($m[25]))
            $parts['path'] = $m[25];

        if (! empty($m[27]))
            $parts['query'] = $m[28];
        if (! empty($m[29]))
            $parts['fragment'] = $m[30];

        if (! $decode)
            return $parts;
        if (! empty($parts['user']))
            $parts['user'] = rawurldecode($parts['user']);
        if (! empty($parts['pass']))
            $parts['pass'] = rawurldecode($parts['pass']);
        if (! empty($parts['path']))
            $parts['path'] = rawurldecode($parts['path']);
        if (isset($h))
            $parts['host'] = rawurldecode($parts['host']);
        if (! empty($parts['query']))
            $parts['query'] = rawurldecode($parts['query']);
        if (! empty($parts['fragment']))
            $parts['fragment'] = rawurldecode($parts['fragment']);
        return $parts;
    }

    /**
     * This function joins together URL components to form a complete URL.
     *
     * RFC3986 specifies the components of a Uniform Resource Identifier (URI).
     * This function implements the specification's "component recomposition"
     * algorithm for combining URI components into a full URI string.
     *
     * The $parts argument is an associative array containing zero or
     * more of the following:
     *
     * "scheme" The scheme, such as "http".
     * "host" The host name, IPv4, or IPv6 address.
     * "port" The port number.
     * "user" The user name.
     * "pass" The user password.
     * "path" The path, such as a file path for "http".
     * "query" The query.
     * "fragment" The fragment.
     *
     * The "port", "user", and "pass" values are only used when a "host"
     * is present.
     *
     * The optional $encode argument indicates if appropriate URL components
     * should be percent-encoded as they are assembled into the URL. Encoding
     * is only applied to the "user", "pass", "host" (if a host name, not an
     * IP address), "path", "query", and "fragment" components. The "scheme"
     * and "port" are never encoded. When a "scheme" and "host" are both
     * present, the "path" is presumed to be hierarchical and encoding
     * processes each segment of the hierarchy separately (i.e., the slashes
     * are left alone).
     *
     * The assembled URL string is returned.
     *
     * Parameters:
     * parts an associative array of strings containing the
     * individual parts of a URL.
     *
     * encode an optional boolean flag selecting whether
     * to do percent encoding or not. Default = true.
     *
     * Return values:
     * Returns the assembled URL string. The string is an absolute
     * URL if a scheme is supplied, and a relative URL if not. An
     * empty string is returned if the $parts array does not contain
     * any of the needed values.
     */
    protected function joinUrl($parts, $encode = TRUE)
    {
        if ($encode) {
            if (isset($parts['user']))
                $parts['user'] = rawurlencode($parts['user']);
            if (isset($parts['pass']))
                $parts['pass'] = rawurlencode($parts['pass']);
            if (isset($parts['host']) && ! preg_match('!^(\[[\da-f.:]+\]])|([\da-f.:]+)$!ui', $parts['host']))
                $parts['host'] = rawurlencode($parts['host']);
            if (! empty($parts['path']))
                $parts['path'] = preg_replace('!%2F!ui', '/', rawurlencode($parts['path']));
            if (isset($parts['query']))
                $parts['query'] = rawurlencode($parts['query']);
            if (isset($parts['fragment']))
                $parts['fragment'] = rawurlencode($parts['fragment']);
        }

        $url = '';
        if (! empty($parts['scheme']))
            $url .= $parts['scheme'] . ':';
        if (isset($parts['host'])) {
            $url .= '//';
            if (isset($parts['user'])) {
                $url .= $parts['user'];
                if (isset($parts['pass']))
                    $url .= ':' . $parts['pass'];
                $url .= '@';
            }
            if (preg_match('!^[\da-f]*:[\da-f.:]+$!ui', $parts['host']))
                $url .= '[' . $parts['host'] . ']'; // IPv6
            else
                $url .= $parts['host']; // IPv4 or name
            if (isset($parts['port']))
                $url .= ':' . $parts['port'];
            if (! empty($parts['path']) && $parts['path'][0] != '/')
                $url .= '/';
        }
        if (! empty($parts['path']))
            $url .= $parts['path'];
        if (isset($parts['query']))
            $url .= '?' . $parts['query'];
        if (isset($parts['fragment']))
            $url .= '#' . $parts['fragment'];
        return $url;
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