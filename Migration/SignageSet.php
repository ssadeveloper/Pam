<?php
namespace Pam\Migration;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Migrations\Version;
use Doctrine\DBAL\Schema\Schema;
use Pam\Aws\S3;
use \Pam\Encryption\Helper as EncHelper;

abstract class SignageSet extends MultiTenancy
{
    protected function isAssetTypeExists($clientId, $code)
    {
        $tableName = 'asset_types' . static::TABLE_SUFFIX;
        $q = "SELECT COUNT(*) FROM `$tableName` WHERE clientId = '$clientId' AND asset_code = '$code'";
        $result = $this->connection->query($q);
        return (bool)$result->fetchColumn();
    }

    protected function getCategoryId($clientId, $code)
    {
        $tableName = 'asset_type_categories' . static::TABLE_SUFFIX;
        $q = "SELECT asset_cat_id FROM `$tableName` WHERE clientId = '$clientId' AND `cat_code`='$code'";
        $result = $this->connection->query($q);
        if ($row = $result->fetch()) {
            return $row['asset_cat_id'];
        }

        return 0;
    }

    protected function getColorId($clientId, $hex)
    {
        $tableName = 'colours' . static::TABLE_SUFFIX;
        $q = "SELECT colour_id FROM `$tableName` WHERE clientId = '$clientId' AND `hex`='$hex'";
        $result = $this->connection->query($q);
        if ($row = $result->fetch()) {
            return $row['colour_id'];
        }

        return 0;
    }

    protected function getAssetTypeId($clientId, $code)
    {
        $tableName = 'asset_types' . static::TABLE_SUFFIX;
        $q = "SELECT asset_type_id FROM `$tableName` WHERE clientId = '$clientId' AND asset_code = '$code'";
        $result = $this->connection->query($q);
        if ($row = $result->fetch()) {
            return $row['asset_type_id'];
        }

        return null;
    }

    protected function getAssetTypeTemplateId($clientId, $assetTypeId, $templateNumber)
    {
        $tableName = $this->getQuotedTableName('asset_type_templates');
        $q = "SELECT template_id FROM $tableName WHERE clientId = ? AND asset_type_id = ? AND template_number = ?";
        $result = $this->connection->executeQuery($q, [$clientId, $assetTypeId, $templateNumber]);

        return $result->fetchColumn();
    }

    protected function getAssetTypeTemplates($clientId, $assetTypeId)
    {
        $tableName = 'asset_type_templates' . static::TABLE_SUFFIX;
        $q = "SELECT * FROM `$tableName` WHERE clientId = '$clientId' AND asset_type_id = '$assetTypeId' ORDER BY template_number";
        $result = $this->connection->query($q);
        $templates = [];
        while ($row = $result->fetch()) {
            $templates[] = $row;
        }

        return $templates;
    }

    protected function hasAssetTypeTemplateCosts($clientId, $templateId, $assetTypeId)
    {
        $tableName = 'asset_costs' . static::TABLE_SUFFIX;
        $q = "SELECT * FROM $tableName WHERE clientId = '$clientId' AND `template_id` = '$templateId' AND `asset_type_id` = '$assetTypeId'";
        $result = $this->connection->query($q);
        return (bool)$result->fetch();
    }

    public function isClientExist($clientId)
    {
        $tableName = 'client';
        $q = "SELECT COUNT(*) FROM `$tableName` WHERE id = '$clientId'";
        $result = $this->connection->query($q);
        return (bool)$result->fetchColumn();
    }

    protected function isAssetTypeAttachmentExist($attachmentId, $targetId, $templateId, $clientId)
    {
        $tableName = 'asset_type_attachments' . static::TABLE_SUFFIX;
        $q = "SELECT COUNT(*) FROM `$tableName` 
WHERE clientId = '$clientId' AND attachment_id = '$attachmentId' AND target_id = '$targetId' AND template_id = '$templateId'";
        $result = $this->connection->query($q);
        return (bool)$result->fetchColumn();
    }

    protected function removeBeforeInsertTriggerIfAny($tableName)
    {
        $existingTriggers = $this->listTriggers();
        $triggerName = "trigger_{$tableName}_before_insert";
        if (array_key_exists($triggerName, $existingTriggers)) {
            $this->removeBeforeInsertTrigger($tableName);
        }
    }

    protected function getAssetsWithType($clientId, $typeId)
    {
        $tableName = 'assets' . static::TABLE_SUFFIX;
        $q = "SELECT * FROM `$tableName` WHERE clientId = '$clientId' AND asset_type_id = '$typeId'";
        $result = $this->connection->query($q);
        $assets = [];
        while ($row = $result->fetch()) {
            $assets[] = $row;
        }

        return $assets;
    }

    protected function addCategory($clientId, $data)
    {
        $tableName = $this->getQuotedTableName('asset_type_categories');
        $q = "SELECT cat_o_id FROM $tableName where clientId = ? ORDER BY cat_o_id DESC LIMIT 1";
        $statement = $this->connection->executeQuery($q, [$clientId]);
        $order = $statement->fetchColumn();
        $order++;

        $data['clientId'] = $clientId;
        $data['cat_o_id'] = $order;
        $this->insertRow('asset_type_categories', $data);
    }

    protected function removeCategory($clientId, $id)
    {
        $tableName = $this->getQuotedTableName('asset_type_categories');
        $q = "DELETE FROM $tableName WHERE clientId = ? AND asset_cat_id = ?";
        $this->addSql($q, [$clientId, $id]);
    }

    protected function addAssetType($clientId, $data)
    {
        $data['clientId'] = $clientId;
        $this->insertRow('asset_types', $data);
    }

    protected function removeAssetType($clientId, $id)
    {
        $tableName = $this->getQuotedTableName('asset_types');
        $q = "DELETE FROM $tableName WHERE clientId = ? AND asset_type_id = ?";
        $this->addSql($q, [$clientId, $id]);
    }

    protected function addAssetTypeTemplate($clientId, $data)
    {
        $data['clientId'] = $clientId;
        $this->insertRow('asset_type_templates', $data);
    }

    protected function removeAssetTypeTemplate($clientId, $id)
    {
        $tableName = $this->getQuotedTableName('asset_type_templates');
        $q = "DELETE FROM $tableName WHERE clientId = ? AND template_id = ?";
        $this->addSql($q, [$clientId, $id]);
    }

    protected function insertRow($tableName, $data)
    {
        $tableName = $this->getQuotedTableName($tableName);

        $columns = implode(', ', array_map([$this->connection, 'quoteIdentifier'], array_keys($data)));

        $q = "INSERT INTO $tableName ($columns) VALUES (?)";

        $this->addSql($q, [array_values($data)], [Connection::PARAM_STR_ARRAY]);
    }

    protected function uploadFileToS3($clientId, $filePath, $fileType)
    {
        if (!is_file($filePath)) {
            return '';
        }

        $fileContent = file_get_contents($filePath);
        S3::setClientId($clientId);

        if (S3::instance()->doesExistByContent($fileContent)) {
            return S3::instance()->getHash($fileContent);
        }

        return S3::instance()->save($fileContent, $fileType) ?: '';
    }

    protected function addAssetTypeTemplatePreview($clientId, $assetCode, $templateNumber)
    {
        $previewDir = __DIR__ . '/../../../scripts/img/asset_preview/';
        $file = $previewDir . $assetCode . "-0$templateNumber.svg";
        if (!is_file($file)) {
            $file = $previewDir . "$clientId/" . $assetCode . "-0$templateNumber.svg";
        }
        echo "File to Upload $file\n\n";
        return $this->uploadFileToS3($clientId, $file, 'svg');
    }

    protected function addAssetTypeTemplateForAssetType($clientId, $assetTypeCode, $data, $textColorHex, $fillColorHex){
        if (!$this->isClientExist($clientId)) {
            return;
        }
        $assetTypeId = $this->getAssetTypeId($clientId, $assetTypeCode);
        if (!$assetTypeId) {
            return;
        }

        $data['asset_type_id'] = $assetTypeId;
        $data['fill_colour_id'] = $this->getColorId($clientId, $textColorHex);
        $data['text_colour_id'] = $this->getColorId($clientId, $fillColorHex);
        $data['img_data'] = $this->addAssetTypeTemplatePreview($clientId, $data['asset_code'], $data['template_number']);
        if (!empty($data['img_data'])) {
            $actionText = 'was';
        } else {
            $actionText = 'wasn\'t';
        }
        echo "Template Preview for {$data['asset_code']} with Number {$data['template_number']} $actionText uploaded to S3\n\n";
        $this->addAssetTypeTemplate($clientId, $data);
    }
    
    protected function removeAssetTypeTemplateForAssetType($clientId, $assetTypeCode, $templateNumber) {
        if (!$this->isClientExist($clientId)) {
            return;
        }
        $assetTypeId = $this->getAssetTypeId($clientId, $assetTypeCode);
        if (!$assetTypeId) {
            return;
        }
        $templateId = $this->getAssetTypeTemplateId($clientId, $assetTypeId, $templateNumber);
        if (!$templateId) {
            return;
        }
        $this->removeAssetTypeTemplate($clientId, $templateId);
    }

}