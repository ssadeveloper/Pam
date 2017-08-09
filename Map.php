<?php

namespace Pam;

class Map
{
    public static function showEditMapControls($userType, $phId = 'all') {
        return edit_asset($userType, $phId);
    }

    public static function showViewMapControlsOnly($userType) {
        return $userType == "diadem-project-manager";
    }    
}