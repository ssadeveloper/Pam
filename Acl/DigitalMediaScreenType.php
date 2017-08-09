<?php

namespace Pam\Acl;

class DigitalMediaScreenType extends Acl
{
    public static function hasAccess($action, $screenTypeId=null, $userId=null)
    {
        return static::checkAccessLevel(ACCESS_LEVEL_ADMIN, $userId);
    }
}