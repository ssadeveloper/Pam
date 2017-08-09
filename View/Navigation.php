<?php

namespace Pam\View;

class Navigation
{
    const DIGITAL_MEDIA_MANAGER = 'digital_media_manager';

    public static function getViews($page)
    {
        static $pages = [
            self::DIGITAL_MEDIA_MANAGER => [
                'dashboard' => 'Dashboard',
                'campus-directory' => 'Campus Directory Manager',
                'building-directory' => 'Building Directory Manager',
                'screen-models' => 'Digital Screen Models',
            ],
        ];
        return array_key_exists($page, $pages) ? $pages[$page] : [];
    }
}