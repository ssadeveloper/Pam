<?php
namespace Pam\Geo;

class Location
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var double
     */
    public $latitude;

    /**
     * @var double
     */
    public $longitude;

    const TABLE_NAME = 'geo_location';

    /**
     * Location constructor.
     * @param $latitude
     * @param $longitude
     * @param int $id
     */
    public function __construct($latitude, $longitude, $id = null)
    {
        $this->latitude = (double)$latitude;
        $this->longitude = (double)$longitude;
        $this->id = $id;
    }

    public function save()
    {
        global $db;
        $latitude = mysqli_real_escape_string($db, $this->latitude);
        $longitude = mysqli_real_escape_string($db, $this->longitude);
        $tableName = static::TABLE_NAME;
        if ($this->id) {
            $id = mysqli_real_escape_string($db, $this->id);
            $query = "UPDATE `{$tableName}` set latitude = '{$latitude}', longitude = '{$longitude}' WHERE id = {$id}";
            mysqli_query($db, $query);
        } else {
            $query = "INSERT INTO `{$tableName}` (latitude, longitude) VALUES ('{$latitude}', '{$longitude}')";
            mysqli_query($db, $query);
            $this->id = mysqli_insert_id($db);
        }
    }

    public function delete()
    {
        if ($this->id) {
            return static::deleteById($this->id);
        }
    }

    public static function deleteById($id)
    {
        global $db;
        $id = mysqli_real_escape_string($db, $id);
        return static::deleteBy("WHERE `id` = '{$id}'");
    }

    public static function deleteByAssets($whereCondition = '') {
        $tableName = static::TABLE_NAME;
        return static::deleteBy("JOIN `assets` ON `assets`.geoLocationId = `{$tableName}`.id {$whereCondition}");
    }

    public static function deleteByMarkers($whereCondition = '') {
        $tableName = static::TABLE_NAME;
        return static::deleteBy("JOIN `markers` ON `markers`.geoLocationId = `{$tableName}`.id {$whereCondition}");
    }

    public static function deleteBy($condition = '') {
        global $db;
        $tableName = static::TABLE_NAME;
        $query = "DELETE `{$tableName}` FROM `{$tableName}` {$condition}";
        return mysqli_query($db, $query);
    }
}