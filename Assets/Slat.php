<?php
namespace Pam\Assets;

use Pam\Model;

class Slat extends Model
{

    protected $tableName = 'asset_slat';

    protected $idColumn = 'slat_id';

    /**
     * @param int $assetId
     * @param int $side
     * @param array $item
     *
     * @return int
     */
    public function insertFromItem($assetId, $side, $item) {
        $dicId = isset($item['id']) ? substr($item['id'], 1) : null;
        $linkedAssetId = isset($item['asset_id']) ? $item['asset_id'] : null;
        $type = $item['type'];
        unset($item['id']);
        unset($item['asset_id']);
        unset($item['type']);
        return $this->insertSlat($assetId, $side, $type, $dicId, $linkedAssetId, $item);
    }

    /**
     * @param array $item
     *
     * @return bool
     */
    public function updateFromItem($item) {
        if (isset($item['slat_id']) && !empty($item['slat_id'])) {
            $slatId = $item['slat_id'];
        } else {
            return false;
        }
        $item['d_id'] = isset($item['id']) ? substr($item['id'], 1) : null;
        $item['linked_asset_id'] = isset($item['asset_id']) && !empty($item['asset_id']) ? $item['asset_id'] : null;
        unset($item['id']);
        unset($item['asset_id']);
        unset($item['slat_id']);
        return $this->update($slatId, $item);
    }

    /**
     * @param array $item
     * @return bool
     */
    public function deleteByItem($item) {
        if (isset($item['slat_id']) && !empty($item['slat_id'])) {
            $this->delete($item['slat_id']);
            return true;
        }
        return false;
    }

    /**
     * Insert new slat to DB filtering $otherData by $this->column array
     *
     * @param int $assetId
     * @param int $side
     * @param string $type
     * @param int $dicId
     * @param int $linkedAssetId
     * @param array $otherData
     * @return int
     */
    public function insertSlat($assetId, $side, $type, $dicId, $linkedAssetId = null, $otherData = []) {
        $commonData = [
            'asset_id' => $assetId, 
            'side' => $side, 
            'type' => $type, 
            'd_id' => $dicId,
            'linked_asset_id' => $linkedAssetId
        ];
        $data = array_merge($commonData, $otherData);
        return $this->insert($data);
    }

    /**
     * Make changes in a slat data to unlink asset
     *
     * @param array $slat
     * @return array
     */
    public function unlinkAssetSlatData(array $slat) {
        if (isset($slat['asset_id']) && $slat['asset_id'] > 0) {
            if ( !($asset = get_asset_with_level($slat['asset_id'])) ){
                return $slat;
            }
            $dicId = Assets::getLinkedAssetDictionaryItemId(null, $asset);
            if (!empty($dicId)) {
                $slat['id'] = '#' . $dicId;
                $slat['asset_id'] = '';
            }
            $slat['room'] = trim(get_asset_special_copy(
                $asset['asset_code_pre'] . ' ' . $asset['asset_code_suf'],
                $asset['building'],
                $asset['l_code_display'],
                $asset['room_no']
            ));
        }
        return $slat;
    }

    /**
     * Unlink slat data in 'slat_data' column and in 'asset_slat' table
     *
     * @param $linkedAssetId
     */
    public function unlinkAssetSlatsByLinkedAssetId($linkedAssetId) {
        $linkedAssetId = mysqli_real_escape_string($this->db, $linkedAssetId);
        $q = "SELECT DISTINCT(`{$this->tableName}`.asset_id), assets.slat_data FROM `{$this->tableName}`
LEFT JOIN assets USING (asset_id)
WHERE linked_asset_id = $linkedAssetId";
        $result = mysqli_query($this->db, $q);
        if (!$result) return;

        $assetsModel = new Assets();
        while ($asset = mysqli_fetch_assoc($result)) {
            $slatData = json_decode($asset['slat_data'], true);
            foreach($slatData as $sK => $side) {
                foreach($side as $slatK => $slat) {
                    if (isset($slat['asset_id']) && $slat['asset_id'] == $linkedAssetId) {
                        $slatData[$sK][$slatK] = $this->unlinkAssetSlatData($slat);
                        $this->updateFromItem($slatData[$sK][$slatK]);
                    }
                }
            }
            $assetsModel->update($asset['asset_id'], ['slat_data' => $slatData]);
        }
    }

    public function getLinkedAssetIds($assetId)
    {
        $assetId = mysqli_real_escape_string($this->db, $assetId);
        $q = "SELECT linked_asset_id FROM `{$this->tableName}` WHERE asset_id = $assetId";
        $result = mysqli_query($this->db, $q);
        if (!$result) return [];

        $linkedAssetIds = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $linkedAssetIds [] = $row['linked_asset_id'];
        }

        return $linkedAssetIds;
    }

    /**
     * Swap 'icons' and 'icons_right' fields of slat
     * @param array $slat
     * @return array
     */
    public static function swapSlatIcons($slat)
    {
        if (isset($slat['icons'])) {
            $icons = $slat['icons'];
        }
        if (isset($slat['icons_right'])) {
            $iconsRight = $slat['icons_right'];
        }
        unset($slat['icons'], $slat['icons_right']);
        if (isset($icons)) {
            $slat['icons_right'] = $icons;
        }
        if (isset($iconsRight)) {
            $slat['icons'] = $iconsRight;
        }
        return $slat;
    }
}