<?php

namespace Pam\Utils;

class Upload
{
    const ERRORS = [
        0 => 'There is no error, the file uploaded with success',
        1 => 'The uploaded file exceeds allowed file size (%%upload_max_filesize%%)',
        2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        3 => 'The uploaded file was only partially uploaded',
        4 => 'No file was uploaded',
        6 => 'Missing a temporary folder',
        7 => 'Failed to write file to disk.',
        8 => 'A PHP extension stopped the file upload.',
    ];

    public static function getErrorString($errorCode)
    {
        if (array_key_exists($errorCode, static::ERRORS)) {
            return str_replace('%%upload_max_filesize%%', ini_get('upload_max_filesize'), static::ERRORS[$errorCode]);
        }
        return 'Unknown error: ' . $errorCode;
    }

    public static function deleteDir($path) {
        $class_func = array(__CLASS__, __FUNCTION__);
        return is_file($path) ?
            @unlink($path) :
            array_map($class_func, glob($path.'/*')) == @rmdir($path);
    }
}