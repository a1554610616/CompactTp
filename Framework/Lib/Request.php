<?php

namespace Framework\Lib;

use Framework\Core\Arr;

class Request extends \Framework\Core\Common
{
    /**
     * @param null $key
     * @return mixed|null
     */
    public static function get($key = null, $default = null)
    {
        if ($key === null) {
            return $_GET;
        }
        return Arr::get($_GET, $key, $default);
    }

    /**
     * @param null $key
     */
    public static function post($key = null, $default = null)
    {
        if ($key === null) {
            return $_POST;
        }
        return Arr::get($_POST, $key, $default);
    }

    /**
     * @param null $key
     * @return mixed|null
     */
    public static function env($key = null)
    {
        $env = $_SERVER === array() ? $_ENV : $_SERVER;
        if ($key === null) {
            return $env;
        }
        return Arr::get($env, $key);
    }

    /**
     * @param null $method
     * @return bool
     */
    public static function method($method = null)
    {
        if ($method === null) {
            return $_SERVER['REQUEST_METHOD'];
        }
        return $_SERVER['REQUEST_METHOD'] === $method;
    }

    /**
     * @return bool
     */
    public static function isAjax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
    }

    /**
     * @return bool
     */
    public static function isFlash()
    {
        return (bool)\preg_match('/^(Shockwave|Adobe) Flash/', $_SERVER['HTTP_USER_AGENT']);
    }

    /**
     * @return bool
     */
    public static function isSSL()
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    }

    /**
     * @return bool
     */
    public static function isMobile()
    {
        static $ua = '(iPhone|iPod|MIDP|AvantGo|BlackBerry|J2ME|Opera Mini|DoCoMo|NetFront|Nokia|PalmOS|PalmSource|portalmmm|Plucker|ReqwirelessWeb|SonyEricsson|Symbian|UP\.Browser|Windows CE|Xiino)';
        return (bool)\preg_match('/' . $ua . '/i', $_SERVER['HTTP_USER_AGENT'], $match);
    }

    /**
     * @return bool
     */
    public static function getClientIp()
    {
        $env = self::env();

        if (isset($env['HTTP_X_FORWARDED_FOR'])) {
            $ips = \explode(',', $env['HTTP_X_FORWARDED_FOR'], 1);
            return $ips[0];
        } elseif (isset($env['HTTP_CLIENT_IP'])) {
            return $env['HTTP_CLIENT_IP'];
        } elseif (isset($env['REMOTE_ADDR'])) {
            return $env['REMOTE_ADDR'];
        }

        return false;
    }
}