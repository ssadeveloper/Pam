<?php
namespace Pam\Config;


class General
{
    /**
     * @var static
     */
    private static $instance;

    private function __construct()
    {}

    /**
     * @return static
     */
    public static function get()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Detects if site should work in demo mode
     * @return bool
     */
    public function isDemo()
    {
        return substr(strtoupper(\Pam\Client::get()->getName()), 0, 4) == "DEMO";
    }

    public function getAppLogFilePath()
    {
        return '/var/log/mediabankpam/app.log';
    }

    public function getLanguages()
    {
        $languages = [
            [
                'language' => 'Simplified Chinese',
                'abbr' => 'zh',
                'active' => 'Y'
            ],
            [
                'language' => 'Korean',
                'abbr' => 'ko',
                'active' => 'N'
            ],
            [
                'language' => 'Arabic',
                'abbr' => 'ar',
                'active' => 'N'
            ],
        ];
        if (\Pam\Client::isClientInitiated() && \Pam\Client::get()->getId() == 'yyz') {
            $languages[0] = [
                'language' => 'French',
                'abbr' => 'fr',
                'active' => 'Y'
            ];
        }
        return $languages;
    }

    public function getHelpCentreUrl()
    {
        return 'https://mediabankpam.atlassian.net/wiki/display/PHC/PAM+%7C+HELP+CENTRE';
    }
}