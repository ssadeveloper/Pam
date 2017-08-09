<?php

namespace Pam\Acl;

use Pam\Client;

class Report extends Acl
{
    public static function hasAccess($action, $siteId=null, $userId=null)
    {
        if (Client::get()->getId() != 'acu') {
            return true;
        }
        $user = static::getUser($userId);
        switch ($user['user_type']) {
            case 'super-admin':
                return true;
            case 'faculty-manager':
                return $action == static::READ;
            case 'installation-project-manager':
            case 'diadem-project-manager':
                if ($action != static::READ) {
                    return false;
                }
                if (!$user['vendor_id']) {
                    return true;
                }
                if (!$siteId) {
                    return false;
                }
                if (is_array($siteId)) {
                    $site = $siteId;
                } else {
                    $site = get_site($siteId);
                }
                if ($site['vendor_id'] == $user['vendor_id']) {
                    return true;
                }
                return false;
            default:
                return false;
        }
    }
}