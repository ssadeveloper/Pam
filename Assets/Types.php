<?php
namespace Pam\Assets;

use Pam\Client;
use Pam\Email\GoogleSMTP;
use Pam\Model;

class Types extends Model
{
    protected $tableName = 'asset_types';

    protected $idColumn = 'asset_type_id';

    const WITH_ARROWS = ['D 1f', 'D 3f', 'D 3w', 'D 4w', 'D 5s', 'D 5w'];
    const WITH_LEVELS = ['N 2w', 'N 3w'];
    const WITH_ROOMS = ['D 2f'];
    const WITH_GROUP_ROOMS = ['D 3f', 'D 3w', 'D 4w', 'D 5s', 'D 5w'];
    const WITH_BUILDING = ['D 4w' => 'B', 'D 5s' => 'B', 'D 5w' => 'B'];

    const NOT_DIRECTORY_ASSET_TYPES = [
        'IN DR' => [
            '25.2-2',
        ],
        'EP ID' => [
            '13.3-1',
            '13.3-2',
        ],
        'IN ID' => [
            '32.1-1',
            '32.1-2',
        ],
        'ID' => [
            '3.1',
            '3.2',
            '30.1',
            '30.2',
            '38.1',
            '60.1',
            '80.1',
            '81.1',
        ],
        'DR' => [
            '56.1',
        ],
        'OP' => [
            '70.1',
            '74.1',
        ],
    ];

    public static function groupAssetTypesByCategories($assetTypes, $categories)
    {
        $result = [];

        foreach ($assetTypes as $assetType) {
            $categoryId = $assetType['asset_cat_id'];
            if (!isset($result[$categoryId])) {
                $result[$categoryId] = $categories['cat_' . $categoryId];
                $result[$categoryId]['asset_types'] = [];
            }
            $result[$categoryId]['asset_types'][] = $assetType;
        }

        return $result;
    }

    public static function makeAssetTypesDescription($assetIds, $assetTypes, $categories)
    {
        if (count($assetIds) == count($assetTypes) || empty($assetIds)) {
            return 'ALL';
        }
        $categoriesWithAssets = static::groupAssetTypesByCategories($assetTypes, $categories);
        $categories = [];
        foreach ($categoriesWithAssets as $category) {
            $assetTypes = [];
            foreach ($category['asset_types'] as $assetType) {
                if (in_array($assetType['asset_type_id'], $assetIds)) {
                    $assetTypes[] = $assetType['asset_code_suf'];
                }
            }
            if (count($assetTypes) == 0) {
                continue;
            }
            if (count($assetTypes) == count($category['asset_types'])) {
                $categories[] = $category['cat_code'];
            } else {
                $categories[] = $category['cat_code'] . ' ' . implode(', ', $assetTypes);
            }
        }

        return implode('; ', $categories);
    }

    public static function fitAssetTypeDescription($description, $length=18)
    {
        if (strlen($description) <= $length) {
            return $description;
        }
        $description = substr($description, 0, $length);

        $semicolonPos = strrpos($description, ';');
        $commaPos = strrpos($description, ',');
        return substr_replace($description, '...', $semicolonPos > $commaPos ? $semicolonPos : $commaPos);
    }

    public static function initNewAssetSlatData($assetType)
    {
        $slatData = [];
        $maxSides = 4;
        for ($i = 1; $i <= $maxSides; $i++) {
            $sideIndex = 'side_' . $i;
            $slatData[$sideIndex] = [];
            if (Client::get()->getId() == 'acu' && $assetType['asset_code'] == 'D 2f') {
                $slatData[$sideIndex][] = [
                    'type' => 'GROUP',
                    'id' => '#',
                    'group_index' => 0,
                ];
                $slatData[$sideIndex][] = [
                    'type' => 'GROUP',
                    'id' => '#',
                    'group_index' => 1,
                ];
            }
        }
        return $slatData;
    }

    public static function getByCode($code)
    {
        global $db;
        $code = mysqli_real_escape_string($db, $code);
        $q = "SELECT * FROM asset_types WHERE asset_code = '$code'";
        $res = mysqli_query($db, $q);
        if (!$res) {
            return null;
        }
        return mysqli_fetch_assoc($res);
    }

    public static function isWatchedByUser($assetTypeId, $userId=null)
    {
        global $db;
        if (!$userId) {
            global $USER_ID;
            $userId = $USER_ID;
        }
        $userId = mysqli_real_escape_string($db, $userId);
        $assetTypeId = mysqli_real_escape_string($db, $assetTypeId);
        $q = "SELECT * FROM asset_type_watchers WHERE asset_type_id = '$assetTypeId' AND user_id = '$userId'";
        $res = mysqli_query($db, $q);
        if (!$res) {
            die(mysqli_error($db));
        }

        return (bool)mysqli_fetch_assoc($res);
    }

    public static function addWatcher($assetTypeId, $userId=null)
    {
        global $db;
        if (!$userId) {
            global $USER_ID;
            $userId = $USER_ID;
        }
        $userId = mysqli_real_escape_string($db, $userId);
        $assetTypeId = mysqli_real_escape_string($db, $assetTypeId);
        $q = "REPLACE INTO asset_type_watchers SET asset_type_id = '$assetTypeId', user_id = '$userId'";
        $res = mysqli_query($db, $q);
        if (!$res) {
            die(mysqli_error($db));
        }
    }

    public static function removeWatcher($assetTypeId, $userId=null)
    {
        global $db;
        if (!$userId) {
            global $USER_ID;
            $userId = $USER_ID;
        }
        $userId = mysqli_real_escape_string($db, $userId);
        $assetTypeId = mysqli_real_escape_string($db, $assetTypeId);
        $q = "DELETE FROM asset_type_watchers WHERE asset_type_id = '$assetTypeId' AND user_id = '$userId'";
        $res = mysqli_query($db, $q);
        if (!$res) {
            die(mysqli_error($db));
        }
    }

    /**
     * @param $assetTypeId
     * @return array of usernames of asset type's watchers
     */
    public static function getWatchers($assetTypeId)
    {
        global $db;
        $assetTypeId = mysqli_real_escape_string($db, $assetTypeId);
        $q = "SELECT username FROM asset_type_watchers aw JOIN users u ON aw.user_id = u.id 
WHERE aw.asset_type_id = '$assetTypeId' AND u.enabled = 'Y'";
        $watchers = [];
        $res = mysqli_query($db, $q);
        if (!$res) {
            die(mysqli_error($db));
        }
        while ($row = mysqli_fetch_assoc($res)) {
            $watchers[] = $row['username'];
        }
        return $watchers;
    }

    public static function notifyWatchers($assetTypeId, $comment)
    {
        $watchers = static::getWatchers($assetTypeId);
        if (count($watchers) == 0) {
            return;
        }
        $assetType = get_asset_type($assetTypeId);
        $text = "$comment<br>{$assetType['asset_code']}";
        foreach ($watchers as $watcher) {
            GoogleSMTP::instance()->mail("MediabankPAM<pam@mediabankpam.com>", $watcher, $comment, $text);
        }
    }

    public function getDirectoryAssetTypeCodes() {
        $allAssetTypes = $this->getAll();
        $result = [];
        foreach($allAssetTypes as $assetType) {
            $result[$assetType['asset_code']] = $assetType['asset_code'];
        }

        foreach(static::NOT_DIRECTORY_ASSET_TYPES as $pre => $suffixes) {
            foreach($suffixes as $suf) {
                unset($result[$pre . ' ' . $suf]);
            }
        }
        
        return $result;
    }
}