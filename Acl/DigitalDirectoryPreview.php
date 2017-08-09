<?php

namespace Pam\Acl;

class DigitalDirectoryPreview extends Acl
{
    public static function hasAccess($action, $buildingCode, $userId=null)
    {
        return static::checkAccessLevel(ACCESS_LEVEL_ADMIN, $userId) ||
            $action == Acl::READ && static::getUser($userId)['isMediaManager'];
    }
}