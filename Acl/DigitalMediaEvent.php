<?php

namespace Pam\Acl;

use Pam\Assets\Events;

class DigitalMediaEvent extends Acl
{
    private static $eventIds = [];

    public static function hasAccess($action, $eventId=null, $userId=null)
    {
        if (static::checkAccessLevel(ACCESS_LEVEL_ADMIN, $userId)) {
            return true;
        }
        $user = static::getUser($userId);
        if (!$user['isMediaManager']) {
            return false;
        }
        if ($action == static::CREATE) {
            return true;
        }
        if ($action == static::READ && !$eventId) {
            return true;
        }

        return in_array($eventId, static::getEventIdsForUser($user));
    }

    private static function getEventIdsForUser($user)
    {
        if (!array_key_exists($user['id'], static::$eventIds)) {
            static::$eventIds[$user['id']] = (new Events())->fetchIdsForUser($user['id']);
        }

        return static::$eventIds[$user['id']];
    }
}