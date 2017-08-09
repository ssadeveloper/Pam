<?php

namespace Pam\Config;

class PhpSettings
{
    public static function getUploadMaxFileSize()
    {
        return static::_toBytes(ini_get('upload_max_filesize'));
    }

    public static function getPostMaxSize()
    {
        return static::_toBytes(ini_get('post_max_size'));
    }

    private static function _toBytes($value) {
        if (is_numeric($value)) {
            return $value;
        }

        $value_length = strlen($value);
        $qty = substr($value, 0, $value_length - 1);
        $unit = strtolower(substr($value, $value_length - 1));
        switch ($unit) {
            case 'k':
                $qty *= 1024;
                break;
            case 'm':
                $qty *= 1048576;
                break;
            case 'g':
                $qty *= 1073741824;
                break;
        }

        return $qty;
    }
}
