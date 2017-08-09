<?php

namespace Pam\Acl;

use Pam\Client;

class RateCard extends Acl
{
    public static function hasAccess($action, $rateCardId, $userId=null)
    {
        if (Client::get()->getId() != 'acu') {
            return true;
        }
        $user = static::getUser($userId);
        switch ($user['user_type']) {
            case 'super-admin':
                return true;
            case 'faculty-manager':
            case 'diadem-project-manager':
                return $action == static::READ;
            case 'installation-project-manager':
                if ($action != static::READ) {
                    return false;
                }
                if (!$user['vendor_id']) {
                    return true;
                }
                $rateCard = get_rate_cards($rateCardId);
                if (!$rateCard['site_id']) {
                    return false;
                }
                $site = get_site($rateCard['site_id']);
                if ($site['vendor_id'] == $user['vendor_id']) {
                    return true;
                }
                return false;
            default:
                return false;
        }
    }
}