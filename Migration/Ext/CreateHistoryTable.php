<?php
namespace Pam\Migration\Ext;

use Doctrine\DBAL\Schema\Schema;

trait CreateHistoryTable {
    public function createHistoryTable(Schema $schema, $tableName, $primaryKey)
    {
        /**
         * @var \Doctrine\DBAL\Connection $connection
         */
        $connection = $this->getConnection();
        $historyTableName = "history_$tableName";
        if ($schema->hasTable($historyTableName)) {
            echo "WARNING: $historyTableName already exists\n\n";
            return;
        }
        $q = "CREATE TABLE `$historyTableName`  LIKE `$tableName`;";
        echo "\n$q\n\n";
        $connection->query($q);

        $q =
            "SELECT COLUMN_TYPE as `type`
FROM information_schema.tables t
JOIN information_schema.columns c ON t.TABLE_NAME = c.TABLE_NAME 
  AND t.TABLE_CATALOG=c.TABLE_CATALOG 
  AND t.TABLE_SCHEMA=c.TABLE_SCHEMA
WHERE t.TABLE_NAME = '$tableName' AND c.COLUMN_NAME = '$primaryKey'";

        $result = $connection->query($q);
        if (!$result) {
            echo "ERROR: $historyTableName wasn't created. There is no $primaryKey column\n\n";
            return;
        }
        $row = $result->fetch();
        $primaryKeyType = strtoupper($row['type']);

        $q = "ALTER TABLE `$historyTableName`
CHANGE COLUMN `$primaryKey` `$primaryKey` $primaryKeyType NOT NULL,
ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
ADD COLUMN `microtime` DATETIME(6) NOT NULL,
DROP PRIMARY KEY,
ADD PRIMARY KEY (`id`);";
        echo "\n$q\n\n";
        $connection->query($q);

        $triggers = ['update' => 'UPDATE', 'delete' => 'DELETE'];
        $clientIdColumn = static::CLIENT_ID_COLUMN;
        foreach ($triggers as $type => $trigger) {
            $triggerName = "{$historyTableName}_{$type}_trigger";
            $q = "DROP TRIGGER IF EXISTS $triggerName";
            echo "\n$q\n\n";
            $connection->exec($q);

            $q =
                "CREATE TRIGGER $triggerName BEFORE $trigger ON $tableName
FOR EACH ROW BEGIN
    INSERT INTO $historyTableName 
        SELECT *, null, NOW(6) FROM $tableName 
        WHERE $primaryKey = OLD.$primaryKey AND $clientIdColumn = OLD.$clientIdColumn;
END";
            echo "\n$q\n\n";
            $connection->exec($q);
        }
    }
}