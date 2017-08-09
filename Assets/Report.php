<?php
namespace Pam\Assets;

use Pam\Client;

class Report
{
    public static function getBulkFilename($type, $sites, $ext, $phase = 'all', $status = 'all') {
        $siteName = 'Various_Sites';
        $buildingName = 'Various_Buildings';

        if (count($sites) == 1) {
            $siteBuildings = reset($sites);
            $firstBuilding = reset($siteBuildings);
            $siteName = $firstBuilding['site_name'];
            if (count($siteBuildings) == 1) {
                $buildingName = $firstBuilding['b_name'];
            }
        }

        $fileName = strtoupper(Client::get()->getId()) . "_PAM_";
        $fileName .= str_replace(' ', '_', "{$siteName}_{$type}_{$buildingName}_");
        if ($phase != "all") {
            $fileName .= "{$phase}_";
        } else {
            $fileName .= "All_Phases_";
        }
        $fileName .= Client::get()->date('Ymd-His') . ".$ext";
        return $fileName;
    }
    /**
     * @param array $slat
     * @param string $assetCodePre
     * @param string $assetCodeSuf
     * @return bool
     */
    public static function putGroupDirAfterNextSlat($slat, $assetCodePre, $assetCodeSuf)
    {
        return !empty($slat)
        && isGroupDirInTheSameLineAsFirstSlat($assetCodePre, $assetCodeSuf)
        && isset($slat['type']) && $slat['type'] == 'GROUP'
        && isset($slat['dir']['icon_pt_code'])
        && in_array($slat['dir']['icon_pt_code'], Icon::getRightAlignedIconCodes());
    }
}