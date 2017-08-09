<?php
namespace Pam\Assets;

class Phase
{
    /**
     * @param string $phIdFrom
     * @param string $phIdTo
     * @return array
     */
    public static function getActionsForChangingPhase($phIdFrom, $phIdTo)
    {
        $actions = [
            '0201' => [
                'hold' => 'Put on Hold',
                'change' => 'Change Content',
                'delete' => 'Delete'
            ]
        ];
        
        return isset($actions[$phIdFrom . $phIdTo]) ? $actions[$phIdFrom . $phIdTo] : [];
    }
    
    public static function getActionLabel($phIdFrom, $phIdTo, $action) {
        $actions = static::getActionsForChangingPhase($phIdFrom, $phIdTo);
        return isset($actions[$action]) ? $actions[$action] : '';
    }

    /**
     * Empty array means no restrictions
     *
     * @param string $userType
     * @return array|string
     */
    public static function filterByUserType($userType)
    {
        return get_review_user($userType) == "installer"
            ? ["02", "03"]
            : [];
    }

    public static function canSee($userType, $phId)
    {
        return get_review_user($userType) != "installer"
        || $phId == "02" || $phId == "03";
    }
    
    public static function getPhases() {
        global $PAM_phases;
        return $PAM_phases;
    }
}