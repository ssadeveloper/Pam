<?php

namespace Pam\Acl;

use Pam\Assets\DigitalDirectory;

class DigitalMediaScreen extends Acl
{
    public static function hasAccess($action, $assetId=null, $userId=null)
    {
        if (static::checkAccessLevel(ACCESS_LEVEL_ADMIN, $userId)) {
            return true;
        }
        $user = static::getUser($userId);
        if ($assetId) {
            return (bool)(new DigitalDirectory())->getOneByUserIdAndAssetId($user['id'], $assetId);
        }

        return false;
    }
}