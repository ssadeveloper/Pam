<?php

namespace Pam\View;

class Alert
{
    public static function set($type, $message)
    {
        $_SESSION['ALERT'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    public static function exists()
    {
        return isset($_SESSION['ALERT']);
    }

    public static function get()
    {
        return isset($_SESSION['ALERT']) ? $_SESSION['ALERT'] : null;
    }

    public static function clear()
    {
        unset($_SESSION['ALERT']);
    }

    public static function pop()
    {
        $alert = static::get();
        static::clear();
        return $alert;
    }
}
