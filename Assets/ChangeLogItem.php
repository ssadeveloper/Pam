<?php

namespace Pam\Assets;

use Pam\Model;

class ChangeLogItem extends Model
{
    protected $tableName = 'assets_change_log_item';

    protected $idColumn = 'id';

    /**
     * @param $side
     * @param $slatNumber
     * @param mixed $item itemId or slat data array from where itemType will be calculated
     * @param string $type
     * @param int|string|null $groupIndex
     * @param int|string|null $slatId
     * @return array
     */
    public static function prepareArray($side, $slatNumber, $item, $type = 'dic', $groupIndex = null, $slatId = null) {
        if (is_array($item)) {
            if (isset($item['group_index'])) {
                $groupIndex = $item['group_index'];
            }
            if (isset($item['slat_id'])) {
                $slatId = $item['slat_id'];
            }
            if (isset($item['type']) && $item['type'] == 'GROUP') {
                $type = 'group';
                $item = $groupIndex;
            } else if (isset($item['asset_id']) && !empty($item['asset_id'])) {
                $item = $item['asset_id'];
                $type = 'asset';
            } else if (isset($item['type']) && $item['type'] == 'TEXT') {
                $item = null;
                $type = 'text';
            } else {
                $item = str_replace('#', '', $item['id']);
                
            }
        }
        $side = str_replace('side_', '', $side);
        $result = ['side' => $side, 'slat' => $slatNumber, 'itemId' => $item, 'itemType' => $type];
        if (!is_null($groupIndex)) {
            $result['groupIndex'] = $groupIndex;
        }

        if (!is_null($slatId)) {
            $result['slat_id'] = $slatId;
        }
        
        return $result;
    }

    /**
     * Insert items for logs
     * 
     * @param array $assetLogs
     * @param array $items [asset_id_1 => [item1], asset_id_2 => [[item1], [item2], [item3]]]
     * @return bool
     */
    public function insertInBulk(array $assetLogs, array $items) {
        $queryInsert = 
            "INSERT INTO `asset_change_log_item` ( `l_id` ,`side`,`slat`,`groupIndex`,`slat_id`,`itemId`,`itemType`) VALUES ";
        foreach ($items as $assetId => $logItems) {
            if (array_key_exists($assetId, $assetLogs)) {
                if (array_key_exists('side', $logItems)) {
                    $logItems = [$logItems];
                }
                foreach ($logItems as $item) {
                    $queryInsert .= $this->prepareInsertForLogs($assetLogs[$assetId]['l_id'], $item);
                }
            }
        }
        $queryInsert = rtrim($queryInsert, ',');
        return $this->db->query($queryInsert) or $result = $this->db->error;
    }

    private function prepareInsertForLogs($lId, $item)
    {
        $lId = $this->db->real_escape_string($lId);
        foreach($item as $k => $v) {
            $item[$k] = $this->db->real_escape_string($v);
        }
        return "($lId,'{$item['side']}','{$item['slat']}'"
                . ',' . (isset($item['groupIndex']) ? (int)$item['groupIndex'] : 'NULL')
                . ',' . (isset($item['slat_id']) ? $item['slat_id'] : 'NULL')
                . ",'{$item['itemId']}','{$item['itemType']}'),";
    }
}