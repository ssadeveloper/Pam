<?php

namespace Pam;

class User extends Model
{
    protected $tableName = 'users';

    protected $idColumn = 'id';

    private static $mediabankpamSuffix = '@mediabankpam.com';

    public static function isMediabankpamUsername($username) {
        return strcmp(substr($username, -strlen(static::$mediabankpamSuffix)), static::$mediabankpamSuffix) === 0;
    }

    public static function formatEmailInterval($interval)
    {
        switch (strtoupper($interval)) {
            case "MONTHLY":
            case "MONTH":
                $interval = "MONTHLY";
                break;
            case "WEEK":
            case "WEEKLY":
                $interval = "WEEKLY";
                break;
            case "DAILY":
            case "DAY":
                $interval = "DAILY";
                break;
            case "HOUR":
            case "HOURLY":
                $interval = "HOURLY";
                break;
            default:
                $interval = "ERROR";
                break;
        }

        return $interval;
    }

    /**
     * @return array
     */
    public function getMediaManagers()
    {
        $q = "SELECT * FROM `{$this->tableName}` WHERE `isMediaManager` = 1 ORDER BY first_name, last_name";

        return $this->fetchRows($q);
    }
}