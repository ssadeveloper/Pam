<?php
namespace Pam\Migration;


use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Migrations\Version;
use Doctrine\DBAL\Schema\Schema;
use \Pam\Encryption\Helper as EncHelper;

abstract class MultiTenancy extends AbstractMigration
{
    const TABLE_SUFFIX = '_table';
    const CLIENT_TABLE = 'client';
    const CLIENT_ID_COLUMN = 'clientId';
    protected $tablesToIgnore;

    public function __construct(Version $version)
    {
        parent::__construct($version);
        $config = $version->getConfiguration();
        $this->tablesToIgnore = [
            static::CLIENT_TABLE,
            $config->getMigrationsTableName(),
            'backup_history',
            MigrateCommand::SYNC_TABLE_NAME
        ];
    }

    /**
     * @param Schema $schema
     * @param callable $action
     */
    protected function applyActionToTables(Schema $schema, callable $action)
    {
        foreach ($schema->getTables() as $table) {
            if (in_array($table->getName(), $this->tablesToIgnore)) {
                continue;
            }
            $action($table);
        }
    }

    /**
     * Creates view for table
     *
     * @param Schema $schema
     * @param string $tableName
     * @param array $createdColumns
     * @param array $removedColumns
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    protected function createViewForTable(Schema $schema, $tableName, $createdColumns = [], $removedColumns = [])
    {
        $viewName = strstr($tableName, static::TABLE_SUFFIX, true);
        $clientIdColumn = static::CLIENT_ID_COLUMN;

        $table = $schema->getTable($tableName);
        $columnNames = [];
        $tableColumnNames = [];
        foreach ($table->getColumns() as $column) {
            $columnName = $column->getName();
            $tableColumnNames[] = $columnName;
            if ($columnName == self::CLIENT_ID_COLUMN || in_array($columnName, $removedColumns)) {
                continue;
            }
            $columnNames[] = "`{$columnName}`";
        }
        
        foreach($createdColumns as $columnName) {
            if (in_array($columnName, $removedColumns) || in_array($columnName, $tableColumnNames)) {
                continue;
            }
            $columnNames[] = "`{$columnName}`";
        }
        
        $selectExpression = implode(', ', $columnNames);

        $createViewSql = <<<SQL
CREATE SQL SECURITY INVOKER VIEW `{$viewName}` AS
SELECT {$selectExpression}
FROM `{$tableName}`
WHERE (`{$tableName}`.`{$clientIdColumn}` = SUBSTRING_INDEX(USER(),'@',1));
SQL;
        $this->addSql($createViewSql);
    }

    /**
     * Drops view for a table
     *
     * @param string $tableName
     */
    protected function dropViewForTable($tableName) {
        $viewName = strstr($tableName, static::TABLE_SUFFIX, true);
        $this->addSql("DROP VIEW `$viewName`;");
    }

    /**
     * @param Schema $schema
     * @param string $tableName
     * @param array $createdColumns
     * @param array $removedColumns
     */
    protected function recreateViewForTable(Schema $schema, $tableName, $createdColumns = [], $removedColumns = [])
    {
        $existingViews = $this->sm->listViews();
        $viewName = strstr($tableName, static::TABLE_SUFFIX, true);
        if (array_key_exists($viewName, $existingViews)) {
            $this->dropViewForTable($tableName);
        }
        $this->createViewForTable($schema, $tableName,  $createdColumns, $removedColumns);
    }

    /**
     * Creates trigger before insert for table
     * 
     * @param string $tableName
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function createBeforeInsertTrigger($tableName)
    {
        $rootDbUser = $this->connection->getUsername();
        $triggerName = "trigger_{$tableName}_before_insert";
        $clientIdColumn = static::CLIENT_ID_COLUMN;
        $createTriggerSql = <<<SQL
CREATE TRIGGER `{$triggerName}`
BEFORE INSERT ON `{$tableName}`
FOR EACH ROW 
BEGIN
  IF SUBSTRING_INDEX(USER(),'@',1) != '{$rootDbUser}' THEN
    SET NEW.{$clientIdColumn} = SUBSTRING_INDEX(USER(),'@',1);
  END IF;  
END;
SQL;
        $this->connection->exec($createTriggerSql);
    }

    /**
     * Removes before insert trigger
     * 
     * @param string $tableName
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function removeBeforeInsertTrigger($tableName) {
        $triggerName = "trigger_{$tableName}_before_insert";
        $this->connection->exec("DROP TRIGGER `{$triggerName}`");
    }

    /**
     * @return array
     */
    protected function listTriggers()
    {
        $database = $this->connection->getDatabase();
        $listTriggersSql = "SELECT * FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = '{$database}'";
        $triggers = $this->connection->fetchAll($listTriggersSql);
        $triggersArray = [];
        foreach ($triggers as $trigger) {
            $triggersArray[$trigger['TRIGGER_NAME']] = $trigger;
        }
        return $triggersArray;
    }

    /**
     * @param Schema $schema
     * @param $viewName
     */
    protected function createViewAndBeforeInsertTrigger(Schema $schema, $viewName)
    {
        $tableName = $viewName . static::TABLE_SUFFIX;
        $existingViews = $this->sm->listViews();
        if (!array_key_exists($viewName, $existingViews)) {
            $this->createViewForTable($schema, $tableName);
        }

        $existingTriggers = $this->listTriggers();
        $triggerName = "trigger_{$tableName}_before_insert";
        if (!array_key_exists($triggerName, $existingTriggers)) {
            $this->createBeforeInsertTrigger($tableName);
        }
    }

    /**
     * @param $viewName
     */
    protected function dropViewAndBeforeInsertTrigger($viewName)
    {
        $tableName = $viewName . static::TABLE_SUFFIX;

        $existingTriggers = $this->listTriggers();
        $triggerName = "trigger_{$tableName}_before_insert";
        if (array_key_exists($triggerName, $existingTriggers)) {
            $this->removeBeforeInsertTrigger($tableName);
        }

        $existingViews = $this->sm->listViews();
        if (array_key_exists($viewName, $existingViews)) {
            $this->dropViewForTable($tableName);
        }
    }

    /**
     * Executes a query for all clients
     *
     * @param string $query Should be without clientId
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    protected function queryAllClients($query) {
        global $db;
        global $hostname_db;
        global $database_db;

        $clientsQuery = "SELECT * FROM client";
        $result = $this->connection->query($clientsQuery);
        // create $db connection for each client
        while($client = $result->fetch()) {
            $dbUserLogin = $client['id'];
            $dbUserPassword = EncHelper::decrypt($client['password']);
            $dbClient = mysqli_connect($hostname_db, $dbUserLogin, $dbUserPassword, $database_db) or die("Error: " . mysqli_error($db));

            mysqli_query($dbClient, $query);

            mysqli_close($dbClient);
        }
    }
    
    protected function getTableName($name){
        return $name . static::TABLE_SUFFIX;
    }

    protected function getQuotedTableName($name)
    {
        return $this->connection->quoteIdentifier($this->getTableName($name));
    }

    /**
     * Adds new column to the table, recreates view by default
     *
     * @param Schema $schema
     * @param string $tableName
     * @param string $columnName
     * @param string $columnDefinition
     * @param bool $recreateView
     */
    protected function addColumn(Schema $schema, $tableName, $columnName, $columnDefinition, $recreateView = true)
    {
        $table = $schema->getTable($tableName);
        if ($table->hasColumn($columnName)) {
            return;
        }
        $this->addSql("ALTER TABLE `{$tableName}` ADD COLUMN `{$columnName}` {$columnDefinition}");
        if ($recreateView) {
            $this->recreateViewForTable($schema, $tableName, [$columnName]);
        }
    }

    /**
     * Drops column from the table, recreates view by default
     *
     * @param Schema $schema
     * @param string $tableName
     * @param string $columnName
     * @param bool $recreateView
     */
    protected function dropColumn(Schema $schema, $tableName, $columnName, $recreateView = true)
    {
        $table = $schema->getTable($tableName);
        if (!$table->hasColumn($columnName)) {
            return;
        }
        $this->addSql("ALTER TABLE `{$tableName}` DROP COLUMN `{$columnName}`");
        if ($recreateView) {
            $this->recreateViewForTable($schema, $tableName, [], [$columnName]);
        }
    }

    /**
     * Change Building Code in the system keeping consistency
     * 
     * @param string $clientId
     * @param string $oldBCode
     * @param string $newBCode
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function changeBuildingCode($clientId, $oldBCode, $newBCode)
    {
        $buildingsTableName = $this->getTableName('site_buildings');
        $assetsTableName = $this->getTableName('assets');
        $markersTableName = $this->getTableName('markers');
        $dictionaryTableName = $this->getTableName('dictionary');
        $assetRequestTableName = $this->getTableName('asset_requests');

        $query = "UPDATE `$buildingsTableName` SET b_code = '{$newBCode}' WHERE b_code = '{$oldBCode}' and clientId = '$clientId'";
        $this->connection->query($query);

        $assetsQuery = "UPDATE `$assetsTableName` SET building = '{$newBCode}' WHERE building = '{$oldBCode}' and clientId = '$clientId'";
        $this->connection->query($assetsQuery);

        $markersQuery = "UPDATE `$markersTableName` SET location_id = '{$newBCode}' 
WHERE location_id = '{$oldBCode}' and location_type = 'building' and clientId = '$clientId'";
        $this->connection->query($markersQuery);

        $dicQuery = "UPDATE `$dictionaryTableName` SET b_code = '{$newBCode}' WHERE b_code = '{$oldBCode}' and clientId = '$clientId'";
        $this->connection->query($dicQuery);

        $selectQuery = "SELECT * FROM `{$assetRequestTableName}` WHERE asset_data LIKE '%building%' and clientId = '$clientId'";
        $result = $this->connection->query($selectQuery);
        while ($assetRequest = $result->fetch()) {
            $assetData = json_decode($assetRequest['asset_data'], true);
            if ($assetData['building'] == $oldBCode) {
                $assetData['building'] = $newBCode;
                $query = "UPDATE `{$assetRequestTableName}` SET asset_data = '{$newBCode}' 
WHERE asset_mr_id = {$assetRequest['asset_mr_id']} and clientId = '$clientId'";
                $this->connection->query($query);
            }
        }

        echo "\nBuilding Code was changed from '{$oldBCode}' to '{$newBCode}' 
        for tables 'site_buildings', 'assets', 'markers', 'dictionary', 'asset_requests'\n\n";
    }

    /**
     * Clone Building with all levels and assets in it
     *
     * @param $clientId
     * @param $bCodeFrom
     * @param $bCodeTo
     */
    public function cloneBuildingWithContent($clientId, $bCodeFrom, $bCodeTo) {
        global $db;
        global $hostname_db;
        global $database_db;

        $buildingsTableName = $this->getTableName('site_buildings');
        $levelsTableName = $this->getTableName('site_levels');
        $assetsTableName = $this->getTableName('assets');
        $markersTableName = $this->getTableName('markers');

        $clientQuery = "SELECT * FROM client WHERE id ='$clientId'";
        $result = $this->connection->query($clientQuery);
        $client = $result->fetch();
        if (!$client) {
            echo "Skipping the cloning of building '{$bCodeFrom}' to '{$bCodeTo}': client with id '{$clientId}' doesn't exist in database\n";
            return;
        }
        $dbUserLogin = $client['id'];
        $dbUserPassword = EncHelper::decrypt($client['password']);
        $db = mysqli_connect($hostname_db, $dbUserLogin, $dbUserPassword, $database_db);

        $q = "INSERT INTO $buildingsTableName
(site_id, o_id, b_code, b_name, address_data, directory_data, geo_code, display, clientId)
SELECT site_id, o_id, '{$bCodeTo}' as b_code, b_name, address_data, directory_data, geo_code, display, clientId
FROM $buildingsTableName
WHERE b_code = '{$bCodeFrom}'";
        mysqli_query($db, $q);
        $bIdTo = mysqli_insert_id($db);

        $q = "SELECT b_id FROM $buildingsTableName WHERE b_code = '$bCodeFrom'";
        $result = $this->connection->query($q);
        $buildingFrom = $result->fetch();
        $bIdFrom = $buildingFrom['b_id'];

        $q = "INSERT INTO $levelsTableName
(unique_id, b_id, o_id, l_code, l_code_display, l_name, display, floorplan_x2, marker_data, l_colour_id, ts, img_data, enableLandscapeAndPoi, defaultMapView, defaultZoomLevel, layersVisibility, clientId)
SELECT unique_id, '{$bIdTo}' as b_id, o_id, l_code, l_code_display, l_name, display, floorplan_x2, marker_data, l_colour_id, ts, img_data, enableLandscapeAndPoi, defaultMapView, defaultZoomLevel, layersVisibility, clientId
FROM $levelsTableName
WHERE b_id = '{$bIdFrom}'";
        mysqli_query($db, $q);
        
        $q = "INSERT INTO $assetsTableName
(asset_type_id, template_id, building, level, lvl_id, ext_id, cell, room_no, room, location, location_x, location_y, location_rot, graphic_x, graphic_y, label_x, label_y, label_b_x, label_b_y, slat_data, digital_message, img_data, asset_data, asset_code, geo_code, tts_data, install_type, install_code, notes_fabrication, notes_install, phase, status_code, display, audit_user, audit_time_required, `order`, colorSchemeCode, kioskMode, clientId, geoLocationId, geoRotation)
SELECT asset_type_id, template_id, '{$bCodeTo}' as building, level, lvl_id, ext_id, cell, room_no, room, location, location_x, location_y, location_rot, graphic_x, graphic_y, label_x, label_y, label_b_x, label_b_y, slat_data, digital_message, img_data, asset_data, asset_code, geo_code, tts_data, install_type, install_code, notes_fabrication, notes_install, phase, status_code, display, audit_user, audit_time_required, `order`, colorSchemeCode, kioskMode, clientId, geoLocationId, geoRotation
FROM $assetsTableName
WHERE building = '{$bCodeFrom}'";
        mysqli_query($db, $q);

        $q = "SELECT asset_id FROM assets WHERE building = '{$bCodeTo}'";
        $result = mysqli_query($db, $q);
        $assetIds = [];
        while($asset = mysqli_fetch_assoc($result)) {
            $assetIds[] = $asset['asset_id'];
        }
        $assetIdsQuery = implode('),(', $assetIds);
        $q = "INSERT INTO `asset_status` (asset_id) VALUES ($assetIdsQuery)";
        mysqli_query($db, $q);
        
        $q = "INSERT INTO $markersTableName
(marker_cat_id, location_type, location_id, plan_id, location, location_x, location_y, location_rot, graphic_x, graphic_y, label_x, label_y, label_b_x, label_b_y, marker_data, marker_status, geo_code, display, clientId, geoLocationId)
SELECT marker_cat_id, location_type, '{$bCodeTo}' as location_id, plan_id, location, location_x, location_y, location_rot, graphic_x, graphic_y, label_x, label_y, label_b_x, label_b_y, marker_data, marker_status, geo_code, display, clientId, geoLocationId
FROM $markersTableName
WHERE location_id = '{$bCodeFrom}'";
        mysqli_query($db, $q);

        mysqli_close($db);
    }

    public function insertDictionaryItem($dicData)
    {
        $dictionaryTable = $this->getTableName('dictionary');

        $insertQuery = "INSERT INTO $dictionaryTable
(dcc_id, cat_id, icon_id, copy, copy_tts, copy_lang_1, copy_lang_2, copy_lang_3, 
copy_approved, tts_approved, copy_lang_1_approved, copy_lang_2_approved, copy_lang_3_approved, 
status, status_code, display, `unique`, b_code, l_code, room_no, clientId)
VALUES 
('{$dicData['dcc_id']}', '{$dicData['cat_id']}', '{$dicData['icon_id']}', '{$dicData['copy']}'
, '{$dicData['copy_tts']}', '{$dicData['copy_lang_1']}', '{$dicData['copy_lang_2']}', '{$dicData['copy_lang_3']}'
, '{$dicData['copy_approved']}', '{$dicData['tts_approved']}', '{$dicData['copy_lang_1_approved']}'
, '{$dicData['copy_lang_2_approved']}', '{$dicData['copy_lang_3_approved']}' 
, '{$dicData['status']}', '{$dicData['status_code']}', '{$dicData['display']}'
, '{$dicData['unique']}', '{$dicData['b_code']}', '{$dicData['l_code']}' 
, '{$dicData['room_no']}', '{$dicData['clientId']}')";

        $this->connection->query($insertQuery);
        return $this->connection->lastInsertId();
    }

    public function launchScript($cmd)
    {
        $descriptorSpec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ];
        
        $process = proc_open($cmd, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            echo "\nERROR: command $cmd not launched\n\n";
        } else {
            fclose($pipes[0]);
            $result = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            echo $result;
        }
    }

    /**
     * @return \Doctrine\DBAL\Connection
     */
    public function getConnection() {
        return $this->connection;
    }

    public function addSql($sql, array $params = [], array $types = [])
    {
        parent::addSql($sql, $params, $types);
    }
}