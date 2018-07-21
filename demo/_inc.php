<?php
require_once 'vendor/autoload.php';
$dir = array(
    __DIR__ . '/output',
    __DIR__ . '/output/clone',
    __DIR__ . '/output/cache'
);
foreach ($dir as $v) {
    if (! is_dir($v)) {
        mkdir($v);
    }
}

if (! function_exists('printr')) {

    /**
     *
     * @param mixed $expression
     */
    function printr()
    {
        foreach (func_get_args() as $v) {
            if (is_scalar($v)) {
                echo $v . "\n";
            } else {
                print_r($v);
            }
        }
        exit();
    }
}

if (! function_exists('vardump')) {

    /**
     *
     * @param mixed $expression
     */
    function vardump()
    {
        call_user_func_array('var_dump', func_get_args());
        exit();
    }
}

class ErrorHandler
{

    /**
     * error to exception
     *
     * @throws \ErrorException
     */
    static function error2exception()
    {
        set_error_handler(
            function ($errno, $errstr, $errfile, $errline) {
                $r = error_reporting();
                if ($r & $errno) {
                    $exception = new \ErrorException($errstr, null, $errno,
                        $errfile, $errline);
                    if ($errno == E_USER_ERROR || $errno == E_RECOVERABLE_ERROR) {
                        throw $exception;
                    }
                    static::catchException($exception);
                }
            });
    }

    /**
     *
     * @param int $severity
     * @return string|null
     */
    static function severity2string($severity)
    {
        static $map;
        if (! isset($map)) {
            $map = get_defined_constants(true);
            $map = $map['Core'];
            foreach (array_keys($map) as $v) {
                if (0 !== strpos($v, 'E_')) {
                    unset($map[$v]);
                }
            }
            $map = array_flip($map);
        }
        if (array_key_exists($severity, $map)) {
            return $map[$severity];
        }
    }

    /**
     * deal with exception
     *
     * @param \Exception $exception
     */
    static function catchException($exception)
    {
        $str = '';
        if ($exception instanceof \ErrorException) {
            $str .= static::severity2string($exception->getSeverity()) . ': ';
        }
        $str .= $exception->__toString();
        if (ini_get('display_errors')) {
            echo $str . "\n\n";
        }
        if (ini_get('log_errors')) {
            error_log($str);
        }
    }
}
ErrorHandler::error2exception();