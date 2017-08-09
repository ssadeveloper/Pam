<?php
namespace Pam\Acl;

abstract class Acl
{
    const CREATE = 'create';
    const READ = 'read';
    const UPDATE = 'update';
    const DELETE = 'delete';

    protected static $users = [];

    protected static function getUser($userId=null)
    {
        if (is_null($userId)) {
            global $USER_ID;
            $userId = $USER_ID;
        }
        if (!array_key_exists($userId, static::$users)) {
            static::$users[$userId] = get_user_by_id($userId);
        }
        return static::$users[$userId];
    }

    public static function checkAccessLevel($requiredAccessLevel, $userId=null)
    {
        $user = static::getUser($userId);
        if (!$user) {
            return false;
        }
        return get_access_level($user['user_type']) <= $requiredAccessLevel;
    }
}