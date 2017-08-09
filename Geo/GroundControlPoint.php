<?php
namespace Pam\Geo;

/**
 * Class GroundControlPoint
 * Represents structure for Ground Control Point (GCP)
 * GCP provides mapping between pixel coordinates (x,y) and real world coordinates (longitude, latitude)
 * @package Pam\Gdal
 */
class GroundControlPoint
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $levelId;

    /**
     * @var int
     */
    public $externalId;

    /** @var  Point */
    public $point;

    /** @var Location */
    public $location;

    const TABLE_NAME = 'ground_control_point';

    /**
     * GroundControlPoint constructor.
     * @param int $x
     * @param int $y
     * @param double $latitude
     * @param double $longitude
     * @param int $levelId
     * @param int $externalId
     * @param int $id
     * @param int $geoLocationId
     */
    public function __construct($x, $y, $latitude, $longitude, $levelId = null, $externalId = null, $id = null, $geoLocationId = null)
    {
        $this->point = new Point($x, $y);
        $this->location = new Location($latitude, $longitude, $geoLocationId);
        $this->levelId = $levelId;
        $this->externalId = $externalId;
        $this->id = $id;
    }

    /**
     * @param $row
     * @return GroundControlPoint
     */
    public static function createByDbRow($row)
    {
        return new GroundControlPoint(
            $row['x'],
            $row['y'],
            $row['latitude'],
            $row['longitude'],
            $row['levelId'],
            $row['externalId'],
            $row['id'],
            $row['geoLocationId']
        );
    }

    public function save()
    {
        global $db;
        $x = mysqli_real_escape_string($db, $this->point->x);
        $y = mysqli_real_escape_string($db, $this->point->y);
        $tableName = static::TABLE_NAME;
        if ($this->id) {
            $this->location->save();
            $id = mysqli_real_escape_string($db, $this->id);
            $query = "UPDATE `{$tableName}` set x = '{$x}', y = '{$y}' WHERE id = {$id}";
            mysqli_query($db, $query);
        } else {
            $this->location->save();
            $geoLocationId = mysqli_real_escape_string($db, $this->location->id);
            $levelId = mysqli_real_escape_string($db, $this->levelId);
            $externalId = mysqli_real_escape_string($db, $this->externalId);
            if ($levelId == '') {
                $levelId = 'NULL';
            } else {
                $levelId = "'" . $levelId . "'";
            }
            if ($externalId == '') {
                $externalId = 'NULL';
            } else {
                $externalId = "'" . $externalId . "'";
            }
            $query = "INSERT INTO `{$tableName}` (x, y, levelId, externalId, geoLocationId) VALUES ('{$x}', '{$y}', {$levelId}, {$externalId}, '{$geoLocationId}')";
            mysqli_query($db, $query);
            $this->id = mysqli_insert_id($db);
        }
    }

    public function delete()
    {
        if ($this->id) {
            static::deleteById($this->id);
        }
    }

    public static function deleteById($id)
    {
        global $db;
        $tableName = static::TABLE_NAME;
        $id = mysqli_real_escape_string($db, $id);
        static::deleteBy("WHERE `{$tableName}`.`id` = '{$id}'");
    }

    public static function deleteByBuildingId($buildingId) {
        global $db;
        $buildingId = mysqli_real_escape_string($db, $buildingId);
        static::deleteByBuilding("WHERE `site_buildings`.`b_id` = '{$buildingId}'");
    }

    public static function deleteByBuilding($whereCondition = '') {
        static::deleteByLevel("JOIN `site_buildings` ON `site_buildings`.`b_id` = `site_levels`.`b_id` {$whereCondition}");
    }

    public static function deleteByLevelId($levelId) {
        global $db;
        $tableName = static::TABLE_NAME;
        $levelId = mysqli_real_escape_string($db, $levelId);
        static::deleteBy("WHERE `{$tableName}`.`levelId` = '{$levelId}'");
    }

    public static function deleteByExternalId($externalId) {
        global $db;
        $tableName = static::TABLE_NAME;
        $externalId = mysqli_real_escape_string($db, $externalId);
        static::deleteBy("WHERE `{$tableName}`.`externalId` = '{$externalId}'");
    }

    public static function deleteByLevel($whereCondition = '') {
        $tableName = static::TABLE_NAME;
        static::deleteBy("JOIN `site_levels` ON `site_levels`.`l_id` = `{$tableName}`.`levelId` {$whereCondition}");
    }

    public static function deleteBy($condition = '') {
        global $db;
        $tableName = static::TABLE_NAME;
        $geoLocationTableName = Location::TABLE_NAME;
        $query = "DELETE `{$tableName}`, `{$geoLocationTableName}` FROM `{$tableName}` 
LEFT JOIN `{$geoLocationTableName}` ON `{$geoLocationTableName}`.`id` = `{$tableName}`.`geoLocationId` {$condition}";
        mysqli_query($db, $query);
    }

    /**
     * @param int $levelId
     * @return bool
     */
    public static function isGroundControlPointExistForLevel($levelId) {
        return !empty(static::findByLevelId($levelId));
    }

    /**
     * @param int $externalId
     * @return bool
     */
    public static function isGroundControlPointExistForExternal($externalId) {
        return !empty(static::findByExternalId($externalId));
    }

    /**
     * @param $levelId
     * @return GroundControlPoint[]
     */
    public static function findByLevelId($levelId)
    {
        global $db;
        $query = "SELECT gcp.*, loc.latitude, loc.longitude FROM `ground_control_point` gcp 
JOIN `geo_location` loc on gcp.geoLocationId = loc.id WHERE levelId = '{$levelId}'";
        $result = mysqli_query($db, $query);
        $gcPoints = [];
        while($row = mysqli_fetch_assoc($result)) {
            $gcPoints[$row['id']] = static::createByDbRow($row);
        }
        return $gcPoints;
    }

    /**
     * @param $externalId
     * @return GroundControlPoint[]
     */
    public static function findByExternalId($externalId)
    {
        global $db;
        $query = "SELECT gcp.*, loc.latitude, loc.longitude FROM `ground_control_point` gcp 
JOIN `geo_location` loc on gcp.geoLocationId = loc.id WHERE externalId = '{$externalId}'";
        $result = mysqli_query($db, $query);
        $gcPoints = [];
        while($row = mysqli_fetch_assoc($result)) {
            $gcPoints[$row['id']] = static::createByDbRow($row);
        }
        return $gcPoints;
    }

    public static function fetchAllForLevels()
    {
        global $db;
        $query = "SELECT gcp.*, loc.latitude, loc.longitude FROM `ground_control_point` gcp 
JOIN `geo_location` loc on gcp.geoLocationId = loc.id WHERE levelId IS NOT NULL";
        $result = mysqli_query($db, $query);
        $gcPoints = [];
        while($row = mysqli_fetch_assoc($result)) {
            if (!array_key_exists($row['levelId'], $gcPoints)) {
                $gcPoints[$row['levelId']] = [];
            }
            $gcPoints[$row['levelId']][$row['id']] = static::createByDbRow($row);
        }
        return $gcPoints;
    }

    public static function fetchAllForExternals()
    {
        global $db;
        $query = "SELECT gcp.*, loc.latitude, loc.longitude FROM `ground_control_point` gcp 
JOIN `geo_location` loc on gcp.geoLocationId = loc.id WHERE externalId IS NOT NULL";
        $result = mysqli_query($db, $query);
        $gcPoints = [];
        while($row = mysqli_fetch_assoc($result)) {
            if (!array_key_exists($row['externalId'], $gcPoints)) {
                $gcPoints[$row['externalId']] = [];
            }
            $gcPoints[$row['externalId']][$row['id']] = static::createByDbRow($row);
        }
        return $gcPoints;
    }
}
