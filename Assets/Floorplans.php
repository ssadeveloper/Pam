<?php

namespace Pam\Assets;

class Floorplans
{
    /**
     * @param array $level 'img_data' column should be json encoded string
     * @param string $cellId to check plan existence for external location
     * @return bool
     */
    public static function isFloorplanExist($level, $cellId = null) {
        if ($level['img_data'] !== '') {
            $imgData = json_decode($level['img_data'], true);
            if ($cellId) {
                return isset($imgData[$cellId]);
            }
            if (isset($imgData['floorplan'])) {
                return true;
            }
        }
        return false;
    }
}