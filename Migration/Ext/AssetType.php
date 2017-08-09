<?php
namespace Pam\Migration\Ext;

trait AssetType {
    public function setSlatsNumber($clientId, $assetTypeCode, $slatNumber)
    {
        if (!$this->isClientExist($clientId)) {
            return;
        }

        $typesTableName = 'asset_types' . static::TABLE_SUFFIX;
        $assetTypeId = $this->getAssetTypeId($clientId, $assetTypeCode);
        if (!$assetTypeId) {
            return;
        }

        $q = "UPDATE $typesTableName SET slats = $slatNumber WHERE clientId = '$clientId' AND asset_type_id = '$assetTypeId'";
        $this->addSql($q);
    }

    public function getAssetTypeId($clientId, $code)
    {
        $tableName = 'asset_types' . static::TABLE_SUFFIX;
        $q = "SELECT asset_type_id FROM `$tableName` WHERE clientId = '$clientId' AND asset_code = '$code'";
        $result = $this->getConnection()->query($q);
        if ($row = $result->fetch()) {
            return $row['asset_type_id'];
        }

        return null;
    }
}