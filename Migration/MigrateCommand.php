<?php

namespace Pam\Migration;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\DBAL\Migrations\Tools\Console\Command;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Connection;

class MigrateCommand extends Command\MigrateCommand
{
    const SYNC_TABLE_NAME = 'doctrine_migration_sync';

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = $this->getMigrationConfiguration($input, $output);
        $configuration->createMigrationTable();

        /** @var Connection $connectionSync */
        $connectionSync = $this->getHelper('connectionSync')->getConnection();
        $this->_createSyncTable($connectionSync);
        $syncTableName = static::SYNC_TABLE_NAME;
        $connectionSync->exec("LOCK TABLE {$syncTableName} WRITE;");
        try {
            return parent::execute($input, $output);
        } finally {
            $connectionSync->exec('UNLOCK TABLES;');
        }
    }

    private function _createSyncTable(Connection $connection)
    {
        if ($connection->getSchemaManager()->tablesExist([static::SYNC_TABLE_NAME])) {
            return;
        }
        $columns = [
            'id' => new Column('id', Type::getType('integer')),
        ];
        $table = new Table(static::SYNC_TABLE_NAME, $columns);
        $table->setPrimaryKey(['id']);
        $connection->getSchemaManager()->createTable($table);
    }
}