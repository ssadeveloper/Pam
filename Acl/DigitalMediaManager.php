<?php

namespace Pam\Acl;

class DigitalMediaManager extends Acl
{
    public static function hasAccess($action, $userId=null)
    {
        return static::checkAccessLevel(ACCESS_LEVEL_ADMIN, $userId) ||
            $action == Acl::READ && static::getUser($userId)['isMediaManager'];
    }
}