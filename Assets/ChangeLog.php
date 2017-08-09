<?php

namespace Pam\Assets;

use Pam\Db\Utils;
use Pam\Model;

class ChangeLog extends Model
{
    protected $tableName = 'assets_change_log';

    protected $idColumn = 'l_id';

    /**
     * Get the last log row for each asset in $assetIds array
     * 
     * @param $assetIds
     * @return array
     */
    public function getLastForAssets($assetIds)
    {
        $q =
"SELECT acl.*
FROM `asset_change_log_table` acl
JOIN (
	SELECT asset_id, MAX(l_id) l_id
    FROM `asset_change_log_table`
	WHERE asset_id IN(" . Utils::arrayToInStatement($assetIds, $this->db) . ")  
    GROUP BY asset_id
    ) aclmax
ON acl.asset_id = aclmax.asset_id AND acl.l_id = aclmax.l_id";
        $result = $this->db->query($q);
        if (!$result) {
            return [];
        }

        $array = [];
        while ($log = $result->fetch_assoc()) {
            $array[$log['asset_id']] = $log;
        }

        return $array;
    }
}