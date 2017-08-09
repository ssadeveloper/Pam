<?php

namespace Pam;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Log {

    /**
     * @var Logger
     */
    private static $logger;

    /**
     * @return Logger
     */
    public static function get()
    {
        if (!static::$logger) {
            $logFilePath = Config\General::get()->getAppLogFilePath();
            $streamHandler = new StreamHandler($logFilePath, Logger::DEBUG);
            static::$logger = new Logger('app_logger', [$streamHandler]);
        }
        return static::$logger;
    }
}