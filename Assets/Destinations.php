<?php

namespace Pam\Assets;
use Pam\Db\Utils;

/**
 * Class Destinations
 * @package Pam\Assets
 */
class Destinations
{
    /**
     * DB connection
     * @var resource
     */
    protected $db;

    public function __construct()
    {
        global $db;
        $this->db = $db;
    }

    /**
     * @param array|null $assetIds null to fetch all assets
     * @return array '{$assetId}-{dictionaryId}' => [
     * {dictionaryId}' => [
     * 'assetId',
     * 'dictionaryId',
     * 'buildingCode',
     * 'levelCode',
     * 'levelCodeDisplay',
     * 'roomNumber',
     * 'copy'
     * ];
     * @internal param string $filter
     */
    public function fetchDestinations($assetIds = null)
    {
        $assets = $this->fetchIdentificationAssets($assetIds);
        $destinations = [];
        $dictionary = Dictionary::get();

        foreach ($assets as $assetId => $asset) {
            if (!is_directory_asset($asset['asset_code_suf'], $asset['asset_code_pre'])) {
                continue;
            }
            $slatData = json_decode($asset['slat_data'], true);
            foreach ($slatData as $sideData) {
                foreach ($sideData as $slat) {
                    if (empty($slat['id']) || !empty($slat['cust']) || $slat['type'] != "OBJ") {
                        continue;
                    }

                    $dictionaryId = substr($slat['id'], 1);
                    $key = "{$assetId}-{$dictionaryId}";
                    if (isset($destinations[$key])) {
                        continue;
                    }

                    $dictionaryItem = $dictionary->getItemOptimizedForBulk($dictionaryId);

                    if (!isset($dictionaryItem['copy']) || $dictionaryItem['copy'] == "") {
                        continue;
                    }
                    if (in_array($dictionaryItem['cat_id'], getIgnoredDictionaryCategoryIds())) {
                        continue;
                    }

                    if ($asset['room_no'] == "" && !empty($dictionaryItem['room_no'])) {
                        $roomNumber = $dictionaryItem['room_no'];
                    } else {
                        $roomNumber = $asset['room_no'];
                    }
                    $levelCodeDisplay = $asset['l_code_display'] == '' ? $asset['level'] : $asset['l_code_display'];

                    $destination = [
                        'assetId' => $assetId,
                        'dictionaryId' => $dictionaryId,
                        'buildingCode' => $asset['building'],
                        'levelCode' => $asset['level'],
                        'levelCodeDisplay' => $levelCodeDisplay,
                        'roomNumber' => $roomNumber,
                        'copy' => $dictionaryItem['copy'],
                    ];

                    $destinations[$key] = $destination;
                }
            }
        }
        return $destinations;
    }

    /**
     * @param array|null $assetIds null to fetch all assets
     * @return array [
     *  'asset_id',
     * 'building',
     * 'level',
     * 'room_no',
     * 'slat_data',
     * 'asset_code_pre',
     * 'asset_code_suf',
     * 'l_code_display',
     * 'b_id',
     * 'l_id',
     * ]
     */
    private function fetchIdentificationAssets($assetIds = null)
    {
        $assetIdsCondition = '';
        if (is_array($assetIds) && !empty($assetIds)) {
            $assetIdsStatement = Utils::arrayToInStatement($assetIds, $this->db);
            $assetIdsCondition = "AND a.asset_id IN ({$assetIdsStatement})";
        }
        $assetCodesStatement = Utils::arrayToInStatement(getIdentificationAssetCategoryCodes(), $this->db);
        $assetCodesCondition = "AND atypes.asset_code_pre IN ({$assetCodesStatement})";

        $query = "SELECT a.asset_id, a.building, a.level, a.room_no, a.slat_data, atypes.asset_code_pre, 
atypes.asset_code_suf, l.l_code_display, b.b_id, l.l_id
FROM assets a 
JOIN asset_types atypes ON a.asset_type_id = atypes.asset_type_id {$assetCodesCondition} {$assetIdsCondition}
JOIN site_buildings b ON b.b_code = a.building
JOIN site_levels l ON l.l_code = a.level and l.b_id = b.b_id
ORDER BY a.building, a.level";
        $result = mysqli_query($this->db, $query) or die(mysqli_error($this->db));

        $assets = [];
        while($row = mysqli_fetch_assoc($result)){
            $assets[$row['asset_id']] = $row;
        };
        return $assets;
    }
}