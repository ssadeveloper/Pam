<?php

namespace Pam\Acl;

class CampusDirectoryManager extends Acl
{
    public static function hasAccess($action, $siteId=null, $userId=null)
    {
        return static::checkAccessLevel(ACCESS_LEVEL_ADMIN, $userId) ||
            $action == Acl::READ && static::getUser($userId)['isMediaManager'];
    }
}