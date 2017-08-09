<?php
namespace Pam;

use Pam\Db\Utils;

class Model
{
    /**
     * Contains array like ['table1' => ['column1', 'column2'], 'table1' => ['column1', 'column2']]
     * Values are set automatically (if not set) when insert/update function is called
     *
     * @var array
     */
    protected static $tableColumns = [];
    
    protected $tableName = '';
    
    protected $idColumn = '';
    
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

    private function getColumns($skipIdColumn=true) {
        if (array_key_exists($this->tableName, static::$tableColumns)) return;
        
        $q = "SELECT c.COLUMN_NAME as `column`
FROM information_schema.tables t
JOIN information_schema.columns c ON t.TABLE_NAME = c.TABLE_NAME 
 AND t.TABLE_CATALOG=c.TABLE_CATALOG 
 AND t.TABLE_SCHEMA=c.TABLE_SCHEMA
WHERE t.TABLE_NAME = '{$this->tableName}'";
        $result = mysqli_query($this->db, $q) or die(mysqli_error($this->db));
        static::$tableColumns[$this->tableName] = [];
        while($row = mysqli_fetch_assoc($result)) {
            if ($row['column'] != $this->idColumn || !$skipIdColumn) {
                static::$tableColumns[$this->tableName][]= $row['column'];
            }
        }
    }

    /**
     * Insert new row to DB filtering $data by $this->column array
     *
     * @param array $data
     * @param bool $onDuplicateUpdate
     * @return int
     */
    public function insert($data, $onDuplicateUpdate=false) {
        $this->getColumns(false);
        $data = array_intersect_key( $data, array_flip( static::$tableColumns[$this->tableName] ) );
        $fields = array_keys($data);
        $values = array_values($data);

        $fieldsString = Utils::arrayToColumnsStatement($fields, $this->db);
        $valuesString = Utils::arrayToInStatement($values, $this->db);
        $q = "INSERT INTO `{$this->tableName}` ($fieldsString) VALUES ($valuesString)";
        if ($onDuplicateUpdate) {
            $q .= " ON DUPLICATE KEY UPDATE " . Utils::arrayToSetStatement($data, $this->db);
        }

        mysqli_query($this->db, $q) or die(mysqli_error($this->db));
        return mysqli_insert_id($this->db);
    }

    /**
     * @param mixed $id
     * @param array $data
     * 
     * @return bool
     */
    public function update($id, $data) {
        $this->getColumns();
        $id = mysqli_real_escape_string($this->db, $id);
        $data = array_intersect_key( $data, array_flip( static::$tableColumns[$this->tableName] ) );
        $q = "UPDATE `{$this->tableName}` SET " . Utils::arrayToSetStatement($data, $this->db)
            . " WHERE `{$this->idColumn}` = '$id'";
        mysqli_query($this->db, $q) or die(mysqli_error($this->db));
        return true;
    }
    
    public function delete($id) {
        $id = mysqli_real_escape_string($this->db, $id);
        $q = "DELETE FROM `{$this->tableName}` WHERE `{$this->idColumn}` = '$id'";
        mysqli_query($this->db, $q) or die(mysqli_error($this->db));
    }

    /**
     * @param $id
     * @return array row with specified id
     */
    public function getOne($id)
    {
        $id = mysqli_real_escape_string($this->db, $id);
        $q = "SELECT * FROM `{$this->tableName}` WHERE `{$this->idColumn}` = '$id' LIMIT 1";
        $res = mysqli_query($this->db, $q);
        if (!$res) {
            die(mysqli_error($this->db));
        }

        return mysqli_fetch_assoc($res);
    }

    /**
     * @param array $orderBy array like ['col1', 'col2', ...] or ['col1' => 'DESC', 'col2' => 'ASC', ...]
     * @return array
     */
    public function getAll($orderBy = [])
    {
        $q = "SELECT * FROM `{$this->tableName}`" . Utils::arrayToOrderByStatement($orderBy);
        return $this->fetchRows($q);
    }

    protected function fetchRows($q)
    {
        $res = mysqli_query($this->db, $q);
        if (!$res) {
            die(mysqli_error($this->db));
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[$row[$this->idColumn]] = $row;
        }
        return $rows;
    }
}