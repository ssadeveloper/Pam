<?php

namespace Pam;

class Features
{
    public static function isGoogleMapsAvailable()
    {
        return static::isFeatureAvailable('google_maps');
    }

    protected static function isFeatureAvailable($name)
    {
        global $db;

        $name = mysqli_real_escape_string($db, $name);
        $sql = "SELECT enabled FROM features WHERE name = '$name'";
        $res = mysqli_query($db, $sql);
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['enabled'];
        }

        return false;
    }
}