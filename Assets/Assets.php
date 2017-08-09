<?php
namespace Pam\Assets;

use Pam\Aws\S3;
use \Pam\Db\Utils;
use Pam\Client;
use Pam\Email\GoogleSMTP;
use Pam\Model;
use Pam\Photo\Directory;

class Assets extends Model
{
    protected $tableName = 'assets';

    protected $idColumn = 'asset_id';
    /**
     * Copy photos from photo directory to an asset
     *
     * @param $assetId
     * @param array $photos
     *
     * @return array Prepared array for asset 'img_data' column with all copied photos and location diagrams
     */
    public static function assignPhotosFromGallery($assetId, array $photos) {
        global $db;
        $asset = get_asset($assetId);

        if(empty($asset['img_data'])) {
            $imgData = ["location-diagrams" => [], "photos" => []];
        } else {
            $imgData = json_decode($asset['img_data'], true);
        }

        $photoAssetArray = [];
        $i = 0;
        foreach($photos as $photo) {
            $galleryPhotoExists = S3::instance()->doesExist($photo['filename'], 'gallery');
            if (!$galleryPhotoExists
                && S3::instance()->doesExist($photo['filename'], 'gallery', S3::$rawImageFolder)) {
                $galleryPhotoExists = Directory::get()->processRawImage($photo);
            }
            if ($galleryPhotoExists) {
                $imgName = S3::instance()->getHash(S3::instance()->getFile($photo['filename'], 'gallery'));
                if (!S3::instance()->doesExist($imgName)) {
                    S3::instance()->copy($photo['filename'], $imgName, 'gallery');
                    S3::instance()->copy($photo['filename'] . '_200', $imgName . '_200', 'gallery');
                }
            } else {
                continue;
            }

            if ($photo['isLocationDiagram'] === true) {
                $type = "location-diagrams";
            } else {
                $type = "photos";
            }

            array_push($imgData[$type], ['name' => $imgName,'ext' => $photo['ext'], 'tags' => []]);
            $photoAssetArray[]= [$photo['photoId'], $assetId, $imgName];
            $i++;
        }
        $imgData = json_encode($imgData);
        $q = "UPDATE `assets` SET img_data = '$imgData' WHERE `asset_id`  = '$assetId'  ";
        mysqli_query($db, $q);

        $q = "INSERT INTO `photo_asset` (`photoId`, `assetId`, `assetImgName`) VALUES ";
        foreach($photoAssetArray as $row) {
            $q .= "('{$row[0]}', '{$row[1]}', '{$row[2]}'),";
        }
        $q = rtrim($q, ',');
        mysqli_query($db, $q);

        return $imgData;
    }

    /**
     * @param $side_data
     * @return array
     */
    public static function regroupSlats($side_data)
    {
        $sideDataGrouped = [];
        foreach ($side_data as $index => $slatData) {
            if ($slatData['type'] == 'GROUP') {
                $groupIndex = $slatData['group_index'];
                if (!isset($sideDataGrouped[$groupIndex])) {
                    $sideDataGrouped[$groupIndex] = [];
                    $sideDataGrouped[$groupIndex]['child_slats'] = [];
                }
                $sideDataGrouped[$groupIndex] += $slatData;
            } elseif (isset($slatData['group_index'])) {
                $groupIndex = $slatData['group_index'];
                if (!isset($sideDataGrouped[$groupIndex])) {
                    $sideDataGrouped[$groupIndex] = [];
                    $sideDataGrouped[$groupIndex]['child_slats'] = [];
                }
                $sideDataGrouped[$groupIndex]['child_slats'][$index] = $slatData;
            }
        }
        ksort($sideDataGrouped);
        return $sideDataGrouped;
    }


    /**
     * Returns building slat from side
     *
     * @param array $sideData
     * @return null|array
     */
    public static function getBuildingSlatAndUnsetIt(&$sideData)
    {
        foreach ($sideData as $k => $slatItem) {
            if ($slatItem['type'] == 'TEXT' && isset($slatItem['building'])) {
                unset($sideData[$k]);
                return $slatItem;
            }
        }
        return null;
    }

    /**
     * Extracts number from building code if it is not numeric
     * 
     * @param string $building
     * @return string
     */
    public static function getBuildingNumberDisplay($building) {
        if (!is_numeric($building) && preg_match_all('/\d+/', $building, $numbers)) {
            $building = end($numbers[0]);
        }
        return $building;
    }

    /**
     * Adds and replaces slat data by dictionary entry data
     * 
     * @param array $slat
     * @param array $dicEntry
     * @return array
     */
    public static function fillSlatByDataFromDictionary($slat, $dicEntry) {
        $slat['copy'] = $dicEntry['copy'];
        $slat['copy_lang_1'] = $dicEntry['copy_lang_1'];
        $slat['cat_id'] = $dicEntry['cat_id'];
        $slat['b_code'] = $dicEntry['b_code'];
        $slat['unique'] = $dicEntry['unique'];
        $slat['icon_id'] = $dicEntry['icon_id'];
        $slat['copy_approved'] = $dicEntry['copy_approved'];
        if ($dicEntry['unique'] == 'Y') {
            $slat['l_code'] = $dicEntry['l_code']; //required for destinations
        }
        
        return $slat;
    }

    /**
     * Changes phase from 01 (planning) to 02 (implementation) without conditions
     * 
     * @param $db
     * @param $assetIds
     */
    public static function changePhaseFromPlanningToInstallation($db, $assetIds) {
        static::changePhase($db, $assetIds, '02', 'Implementation');
    }

    /**
     * Changes phase from 01 (planning) to 03 (maintenance) without conditions
     *
     * @param $db
     * @param $assetIds
     */
    public static function changePhaseFromPlanningToMaintenance($db, $assetIds) {
        static::changePhase($db, $assetIds, '03', 'Maintenance');
    }

    private static function changePhase($db, $assetIds, $newPhaseCode, $newPhaseName) {
        $query = "UPDATE `assets` SET status_code = '{$newPhaseCode}.00.0000.00.00'  WHERE asset_id IN (" .
            Utils::arrayToInStatement($assetIds, $db). ") ";
        mysqli_query($db, $query) or die(mysqli_error($db));
        $comment = "Phase Change from Planning to $newPhaseName";
        $comment_type = "stup_{$newPhaseCode}.00.0000.00.00_phch";
        setChangeLogInBulk($comment, $comment_type, $assetIds);
    }

    /**
     * Check are all added slats ready for client review in planning phase
     * 
     * @param array $asset
     * @return bool
     */
    public static function isReadyForPlanningClientReview(array $asset) {
        $dictionary = Dictionary::get();
        $slat_data = json_decode($asset['slat_data'], true);
        if (!empty($slat_data)) {
            foreach ($slat_data as &$si_data) {
                if (empty($si_data)) {
                    continue;
                }
                foreach ($si_data as $k => $slat) {
                    if (isset($slat['id']) && $slat['type'] == "OBJ" && !isset($slat['blank'])) {
                        $dic_id = substr($slat['id'], 1);//remove the #
                        $dic_entry = $dictionary->getItemOptimizedForBulk($dic_id);
                        if (empty($dic_entry)) {
                            continue;
                        }
                        if ($dic_entry['copy_approved'] != 'Y') {
                            return false;
                        }
                    }
                }
            }
        }
        
        return true;
    }
    
    public static function getStatusOutcomeLabel($status) {
        switch($status) {
            case 'n/a':
                $outcomeLabel = "Remaining";
                break;
            case 'client_review';
                $outcomeLabel = "Ready for Review";
                break;
            default:
                $outcomeLabel = ucfirst($status);
                break;
        }
        
        return $outcomeLabel;
    }
    
    public static function showPlanningReadyForClientReview($USER_TYPE) {
        $appropriateUserType = get_review_user($USER_TYPE) == 'client';
        return $appropriateUserType && in_array(Client::get()->getId(), ['acu']);
    }
    
    public static function clearClientReviewByDictionaryItemId($db, $PAM_phases, $dicId) {
        $condition = " AND ( a.status_code LIKE '_1.__._1%' OR a.status_code LIKE '_2.__._1%')";
        $assets = get_asset_by_dictionary($dicId, "asset_id, status_code", $condition);
        static::clearClientReview($db, $PAM_phases, $assets);
    }

    public static function clearClientReviewByAssetId($db, $PAM_phases, $assetId, $comment) {
        $asset = get_asset($assetId);
        static::clearClientReview($db, $PAM_phases, [$asset], $comment);
    }
    
    public static function clearClientReview($db, array $assets, $commentDesc = 'Dictionary Changed') {
        $reviewUser = static::getClientReviewUser();
        foreach($assets as $asset) {
            if (!static::isApprovedByClient($asset['status_code']) 
                || ! static::isInPhase($asset['status_code'], ['01', '02']))
                continue;
            
            $asset['status_code'][7] = '0';
            $asset['status_code'][12] = '2';
            $asset['asset_id'] = mysqli_real_escape_string($db, $asset['asset_id']);
            $q = "UPDATE assets SET status_code = '{$asset['status_code']}' WHERE asset_id = {$asset['asset_id']}";
            $updateResult = mysqli_query($db, $q) or die(mysqli_error($db));
            $statusArray = explode('.', $asset['status_code']);
            $phase = Phase::getPhases()[$statusArray[0]];
            $comment = "$phase $reviewUser Review Pending - $commentDesc";
            $commentType = 'stup_' . $asset['status_code'] . "_resolveissue";
            set_change_log($comment, $commentType, $asset['asset_id'], $timestamp = "");
        }
    }
    
    public static function isApprovedByClient($statusCode) {
        return $statusCode[7] == '1';
    }

    public static function isInPhase($statusCode, $phase) {
        $phase = (array) $phase;
        $statusArray = explode('.', $statusCode);
        
        return in_array($statusArray[0], $phase);
    }

    /**
     * Put new item to appropriate position in array depending on group index if it's set
     *
     * @param array $slats
     * @param array $item
     * @param int $startSlatIndex
     * @param int $maxSlatIndex
     * @return array
     */
    public static function putNewSlatItem($slats, $item, $startSlatIndex = 0, $maxSlatIndex = null) {
        if (array_key_exists('group_index', $item) && $item['type'] != 'GROUP') {
            $lastK = -1;
            foreach($slats as $k => $slat) {
                if (array_key_exists('group_index', $slat) && $slat['group_index'] == $item['group_index']) {
                    $lastK = $k;
                }
            }
            if ($lastK >= 0) {
                array_splice( $slats, $lastK + 1, 0, [$item] );
            } else {
                array_push($slats, $item);
            }
        } else {
            $maxSlatIndex = is_null($maxSlatIndex) ? max(array_keys($slats)) : $maxSlatIndex;
            //add new slat item to the first empty position in asset slats starting from $startSlatIndex position
            for ($i = (int)$startSlatIndex; $i <= $maxSlatIndex + 1; $i++) {
                if (!array_key_exists($i, $slats)) {
                    $slats[$i] = $item;
                    break;
                }
            }
        }
        return $slats;
    }

    public static function sortSideDataByGroupIndex($slats) {
        if (!is_array($slats)) return $slats;
        
        $requireSort = false;
        foreach($slats as $slat) {
            if (isset($slat['group_index'])) {
                $requireSort = true;
                break;
            }
        }
        if (!$requireSort) return $slats;

        foreach($slats as $k => &$slat) {
            $slat['order_key'] = $k;
        }
        usort($slats, function($a, $b) {
            $aWeight = isset($a['group_index']) ? $a['group_index'] * 10000 : -10000;
            $aWeight += isset($a['type']) && $a['type'] != 'GROUP' && $a['type'] != 'TEXT' ? 5000 : 0;
            $aWeight += $a['order_key'];
            $bWeight = isset($b['group_index']) ? $b['group_index'] * 10000 : -10000;
            $bWeight += isset($b['type']) && $b['type'] != 'GROUP' && $b['type'] != 'TEXT' ? 5000 : 0;
            $bWeight += $b['order_key'];
            if ($aWeight == $bWeight) {
                return 0;
            }
            return ($aWeight < $bWeight) ? -1 : 1;
        });
        foreach($slats as $k => &$slat) {
            unset($slat['order_key']);
        }
        return $slats;
    }

    public static function fillSlatData($slat_data)
    {
        foreach ($slat_data as &$si_data) {
            if (empty($si_data)) {
                continue;
            }
            foreach ($si_data as $k => $slat) {
                if (!isset($slat['id']) || $slat['type'] != "OBJ" || isset($slat['blank'])) {
                    continue;
                }
                $dic_id = substr($slat['id'], 1); //remove the #
                $dic_entry = get_dictionary_item($dic_id);
                if (empty($dic_entry)) {
                    continue;
                }
                $si_data[$k] = static::fillSlatByDataFromDictionary($slat, $dic_entry);
            }

        }

        return $slat_data;
    }

    public static function getClientReviewUser()
    {
        return Client::get()->getId() == 'yyz' ? 'Peer' : 'Client';
    }
    
    public static function showChangingPhaseFromPlanningToMaintenance() {
        return Client::get()->getId() == 'yyz';
    }

    public static function groupSideDataByLvl($slats) {
        if (!is_array($slats)) return $slats;

        $sortedSlats = [-1 => []];
        foreach($slats as $k => $slat) {
            $slat['slatIndex'] = $k;
            if (!isset($slat['lvl'])) {
                $sortedSlats[-1][]= $slat;
            } else {
                if (!array_key_exists($slat['lvl'], $sortedSlats)) {
                    $sortedSlats[$slat['lvl']] = [];
                }
                $sortedSlats[$slat['lvl']][] = $slat;
            }
        }

        return $sortedSlats;
    }
    
    public static function sortSideDataByLvl($slats) {
        $slats = static::groupSideDataByLvl($slats);
        $sortedSlats = [];
        foreach($slats as $lvlSlats) {
            foreach($lvlSlats as $slat) {
                $sortedSlats[] = $slat;
            }
        }
        
        return $sortedSlats;
    }

    public static function getLinkedAssetCopy($assetId, $asset = [])
    {
        $dicItemId = static::getLinkedAssetDictionaryItemId($assetId, $asset);
        $dicItem = get_dictionary_item($dicItemId);
        return !empty($dicItem) ? static::getLinkCopy($dicItem['copy'], $asset) : null;
    }
    
    public static function getLinkedAssetDictionaryItemId($assetId, $asset = []) {
        if (empty($asset)) {
            $asset = get_asset_with_level($assetId);
        }
        $slatData = json_decode($asset['slat_data'], true);
        if (!isset($slatData['side_1']) || empty($slatData['side_1'])) return '';

        $firstSlat = array_shift($slatData['side_1']);
        return substr($firstSlat['id'], 1);
    }

    public static function fillSlatByDataFromAsset($slat, $assetId, $asset = []) {
        if (empty($asset)) {
            $asset = get_asset_with_level($assetId);
            if ($asset == null) {
                return ['type' => 'OBJ', 'asset_id' => $assetId, 'blank' => 1];
            }
        }
        $slatData = json_decode($asset['slat_data'], true);
        if (!isset($slatData['side_1']) || empty($slatData['side_1'])) return '';

        $firstSlat = array_shift($slatData['side_1']);
        $dicItemId = substr($firstSlat['id'], 1);
        $dicItem = get_dictionary_item($dicItemId);

        $slat['copy'] = $dicItem['copy'];
        $slat['room'] = get_asset_special_copy(
            $asset['asset_code_pre'] . ' ' . $asset['asset_code_suf'],
            $asset['building'],
            $asset['l_code_display'],
            $asset['room_no']
        );

        return $slat;
    }

    public static function getLinkCopy($copy, $asset) {
        return $copy . ' '
        . get_asset_special_copy(
            $asset['asset_code_pre'] . ' ' . $asset['asset_code_suf'],
            $asset['building'],
            $asset['l_code_display'],
            $asset['room_no']
        );
    }

    public static function setKioskMode($assetId, $mode)
    {
        global $db;
        $assetId = mysqli_real_escape_string($db, $assetId);
        $mode = mysqli_real_escape_string($db, $mode);
        $q = "UPDATE assets SET kioskMode = '$mode' WHERE asset_id = '$assetId'";
        return mysqli_query($db, $q);
    }

    /**
     * Perform actions after content is changed
     * 
     * @param array $assets array of assets
     * @param string $comment Don't check Client Review if it isn't set 
     * @param string|null $changeLogCommentType Don't put change log if it isn't set
     * @param array $logItems [asset_id_1 => [...], asset_id_2 => [...]]
     */
    function contentChanged($assets, $comment = null, $changeLogCommentType = null, array $logItems = [])
    {
        $assetIds = [];
        foreach ($assets as $asset) {
            $assetIds [] = $asset['asset_id'];
            $data = [];
            if (isset($asset['install_type']) && $asset['install_type'] == 'INIT') {
                $data['install_type'] = 'New';
            }
            if (!empty($data)) {
                $this->update($asset['asset_id'], $data);
            }
        }
        if (is_null($changeLogCommentType)) {
            $changeLogCommentType = 'undefined';
        }
        
        $comment = $this->db->real_escape_string($comment);
        setChangeLogInBulk($comment, $changeLogCommentType, $assetIds, "", $logItems);

        if (!is_null($comment)) {
            static::clearClientReview(
                $this->db,
                $assets,
                $comment
            );
        }
        $this->notifyWatchers($assets, $comment);
    }

    public static function isWatchedByUser($assetId, $userId=null)
    {
        global $db;
        if (!$userId) {
            global $USER_ID;
            $userId = $USER_ID;
        }
        $userId = mysqli_real_escape_string($db, $userId);
        $assetId = mysqli_real_escape_string($db, $assetId);
        $q = "SELECT * FROM asset_watchers WHERE asset_id = '$assetId' AND user_id = '$userId'";
        $res = mysqli_query($db, $q);
        if (!$res) {
            die(mysqli_error($db));
        }

        return (bool)mysqli_fetch_assoc($res);
    }

    public static function addWatcher($assetId, $userId=null)
    {
        global $db;
        if (!$userId) {
            global $USER_ID;
            $userId = $USER_ID;
        }
        $userId = mysqli_real_escape_string($db, $userId);
        $assetId = mysqli_real_escape_string($db, $assetId);
        $q = "REPLACE INTO asset_watchers SET asset_id = '$assetId', user_id = '$userId'";
        $res = mysqli_query($db, $q);
        if (!$res) {
            die(mysqli_error($db));
        }
    }

    public static function removeWatcher($assetId, $userId=null)
    {
        global $db;
        if (!$userId) {
            global $USER_ID;
            $userId = $USER_ID;
        }
        $userId = mysqli_real_escape_string($db, $userId);
        $assetId = mysqli_real_escape_string($db, $assetId);
        $q = "DELETE FROM asset_watchers WHERE asset_id = '$assetId' AND user_id = '$userId'";
        $res = mysqli_query($db, $q);
        if (!$res) {
            die(mysqli_error($db));
        }
    }

    /**
     * @param $assetId
     * @return array of usernames of asset's watchers
     */
    public static function getWatchers($assetId)
    {
        global $db;
        $assetId = mysqli_real_escape_string($db, $assetId);
        $q = "SELECT username FROM asset_watchers aw JOIN users u ON aw.user_id = u.id 
WHERE aw.asset_id = '$assetId' AND u.enabled = 'Y'";
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

    protected function notifyWatchers($assets, $comment)
    {
        foreach ($assets as $asset) {
            $watchers = static::getWatchers($asset['asset_id']);
            if (count($watchers) == 0) {
                continue;
            }
            $asset = get_asset($asset['asset_id']);
            $text = "$comment<br>\n{$asset['building']} - {$asset['level']} - {$asset['asset_code_pre']} {$asset['asset_code_suf']} #{$asset['location']}\n";
            foreach ($watchers as $watcher) {
                GoogleSMTP::instance()->mail("MediabankPAM<pam@mediabankpam.com>", $watcher, $comment, $text);
            }
        }
    }

    public static function getLinkedAssetIds($assetIds)
    {
        global $db;
        $assetIds = Utils::arrayToInStatement($assetIds, $db);
        $q = "SELECT * FROM asset_attachments WHERE attachment_id IN ($assetIds) OR target_id IN ($assetIds)";
        $res = mysqli_query($db, $q);
        if (!$res) die(mysqli_error($db));
        $linkedAssetIds = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $linkedAssetIds[] = $row['attachment_id'];
            $linkedAssetIds[] = $row['target_id'];
        }
        return array_unique($linkedAssetIds);
    }
    
    public function getAllFiltered(array $bCodes, array $assetTypes) {
        $result = $this->db->query(
"SELECT assets.*  ,asset_code_pre, asset_code_suf, asset_cat_id 
FROM `assets` 
JOIN asset_types ON assets.asset_type_id = asset_types.asset_type_id 
WHERE building IN (" . Utils::arrayToInStatement($bCodes, $this->db) . ") 
      AND asset_types.asset_code IN (" . Utils::arrayToInStatement($assetTypes, $this->db) . ")
ORDER BY level, location;");

        $array = array();
        while ($row = $result->fetch_assoc()) {
            $row['asset_data'] = trim(preg_replace('/(\r\n)|\n|\r/', '<br/>', $row['asset_data']));
            $array[$row['asset_id']] = $row;
        };

        return $array;
    }

    public static function checkImageInfo($imageInfo)
    {
        if (!isset($imageInfo['name'], $imageInfo['ext'])) {
            error_log('Asset image doesn\'t contain required keys: ' . json_encode($imageInfo));
            return false;
        }
        return true;
    }
}