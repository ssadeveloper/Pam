<?php

namespace Pam\Acl;

class BuildingDirectoryManager extends Acl
{
    protected static $userCodes = [];

    /**
     * @param $action
     * @param string|array $buildingCode
     * @param int $userId
     * @return bool true if user has an access to any of building with specified codes.
     */
    public static function hasAccess($action, $buildingCode, $userId = null)
    {
        if (static::checkAccessLevel(ACCESS_LEVEL_ADMIN, $userId)) {
            return true;
        }

        $user = static::getUser($userId);
        if (!$user['isMediaManager']) {
            return false;
        }

        $codes = static::getBuildingCodesForUser($user);
        if (is_array($buildingCode)) {
            return !empty(array_intersect($buildingCode, $codes));
        } else {
            return in_array($buildingCode, $codes);
        }
    }

    private static function getBuildingCodesForUser($user)
    {
        if (array_key_exists($user['id'], static::$userCodes)) {
            return static::$userCodes[$user['id']];
        }
        global $db;
        $userId = mysqli_real_escape_string($db, $user['id']);
        $q = "SELECT DISTINCT building FROM assets a JOIN digital_directory_asset dda ON a.asset_id = dda.assetId WHERE dda.userId = '$userId'";
        $res = mysqli_query($db, $q);
        if (!$res) {
            die(mysqli_errno($db));
        }
        $codes = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $codes[] = $row['building'];
        }
        static::$userCodes[$user['id']] = $codes;

        return $codes;
    }

}