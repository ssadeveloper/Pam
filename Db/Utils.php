<?php
namespace Pam\Db;

class Utils
{
    /**
     * Converts array of values into string which can be used in SQL 'WHERE IN' statemement:
     *  ['value1', 'value2', 'value3'] converts into string with properly escaped values: "'value1', 'value2', 'value3'"
     * @param array $values
     * @param $db
     * @return string
     */
    public static function arrayToInStatement($values, $db = null) {
        $escapedValues = array_map(function($value) use ($db) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            return is_null($db) ? $value : mysqli_real_escape_string($db, $value);
        }, $values);
        return "'" . implode("', '", $escapedValues) . "'";
    }

    /**
     * Converts array of columns names into string which can be used in SQL:
     *  ['column1', 'column2', 'column3'] converts into string with properly escaped values: "`column1`, `column2`, `column3`"
     * 
     * @param array $columns
     * @param $db
     * @return string
     */
    public static function arrayToColumnsStatement($columns, $db) {
        $escapedColumns = array_map(function($column) use ($db) {
            return mysqli_real_escape_string($db, $column);
        }, $columns);
        return "`" . implode("`,`", $escapedColumns) . "`";
    }

    /**
     * Converts associated array into SET statement:
     * ['column1' => 'value1', 'column2' => 'value2'] into escaped string "`column1`='value1',`column2`='value2'"
     * 
     * @param array $data
     * @param \mysqli $db
     * @return string
     */
    public static function arrayToSetStatement($data, $db) {
        $preparedData = array_map(function($key, $value) use ($db) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            return "`" . mysqli_real_escape_string($db, $key). "`='" . mysqli_real_escape_string($db, $value) . "'";
        }, array_keys($data), array_values($data));
        return implode(",", $preparedData);
    }

    /**
     * Converts columns array to 'ORDER BY' statement
     * @param array $orderBy array like ['col1', 'col2', ...] or ['col1' => 'DESC', 'col2' => 'ASC', ...]
     * @return string
     */
    public static function arrayToOrderByStatement($orderBy) {
        $result = '';
        if (!empty($orderBy)) {
            $strOrderBy = [];
            foreach ($orderBy as $key => $value) {
                if (is_int($key)) {
                    $strOrderBy[] = "`$value`";
                } else {
                    $strOrderBy[] = "`$key` $value";
                }
            }
            $result = " ORDER BY " . implode(', ', $strOrderBy);
        }
        return $result;
    }

    /**
     * @param string $auditLevelsOrder raw column value
     * @return array
     */
    public static function prepareAuditLevelsOrderArray($auditLevelsOrder) {
        $auditLevelsOrder = json_decode($auditLevelsOrder, true);
        if (!is_array($auditLevelsOrder)) {
            $auditLevelsOrder = [];
        }
        $tempArray = [];
        foreach($auditLevelsOrder as $building) {
            $k = key($building);
            $tempArray[$k] = $building[$k];
        }
        return $tempArray;
    }
}