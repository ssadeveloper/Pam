<?php

namespace Pam\Photo;

use Pam\Db\Utils;
use Pam\Aws\S3;

class Tags
{
    /**
     * @var Tags
     */
    private static $instance;

    /**
     * DB connection
     * @var resource
     */
    private $db;

    private function __construct()
    {
        global $db;
        $this->db = $db;
    }

    /**
     * @return Tags
     */
    public static function get()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * @param array $tags
     * @return bool
     */
    public static function isLocationDiagram($tags) {
        if (is_null($tags)) return false;
        
        foreach($tags as $tagId => $tagName) {
            if (str_replace([' ', '-', '_'], '', strtolower($tagName)) === 'locationdiagram') {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array
     */
    public function getTags()
    {
        $query = 'SELECT * from `tag`';
        $result = mysqli_query($this->db, $query);
        $tags = [];
        while($tag = mysqli_fetch_assoc($result)){
            $tags[$tag['id']] = $tag['name'];
        }
        return $tags;
    }

    /**
     * @param $tagName
     * @return int|string
     */
    public function addTag($tagName)
    {
        $name = mysqli_real_escape_string($this->db, $tagName);
        $query = "INSERT INTO `tag` (`name`) VALUES ('{$name}')";
        mysqli_query($this->db, $query); //TODO: check result
        return mysqli_insert_id($this->db);
    }

    /**
     * @param $tagName
     * @return bool|\mysqli_result
     */
    public function removeTag($tagName)
    {
        $name = mysqli_real_escape_string($this->db, $tagName);
        $query = "DELETE from `tag` WHERE `name` = '{$name}'";
        return mysqli_query($this->db, $query);
    }

    /**
     * Assigns tags to photos, clears existing assignments
     * @param $photoIds
     * @param $tagIds
     */
    public function assignTags($photoIds, $tagIds)
    {
        $this->clearTags($photoIds);
        foreach ($tagIds as $tagId) {
            foreach ($photoIds as $photoId) {
                $this->assignTag($photoId, $tagId);
            }
        }
    }

    /**
     * @param array $filter
     * @param int $start
     * @param int $count
     * @param bool $withAssets Add assigned asset ids in 'asset' array for each photo
     * @return array
     */
    public function getPhotosWithTags($filter = [], $start = null, $count = null, $withAssets = false)
    {
        $query = $this->buildSelectPhotosSqlQuery($filter, $start, $count);
        $photos = $this->fetchPhotos($query);

        if ($withAssets === true) {
            $photoIds = array_keys($photos);
            $photoIdsStatement = Utils::arrayToInStatement($photoIds, $this->db);

            $query =
                "SELECT photoId, assetId, site_id, building, level, room_no, status_code, asset_code, location
                 FROM `photo_asset` 
                 LEFT JOIN `assets` ON (`assets`.asset_id = `photo_asset`.assetId)
                 LEFT JOIN `site_buildings` ON (assets.`building` = `site_buildings`.`b_code`)
                 WHERE `photoId` IN ({$photoIdsStatement})";
            $result = mysqli_query($this->db, $query);
            while ($result && $photoAsset = mysqli_fetch_assoc($result)) {
                if (!array_key_exists('assets', $photos[$photoAsset['photoId']])) {
                    $photos[$photoAsset['photoId']]['assets'] = [];
                }
                $photos[$photoAsset['photoId']]['assets'][] = $photoAsset;
            }
        }
        
        return $photos;
    }

    /**
     * @param string $query
     * @return array
     */
    private function fetchPhotos($query)
    {
        $result = mysqli_query($this->db, $query);
        $photos = [];
        while ($result && $photo = mysqli_fetch_assoc($result)) {
            if ($photo['tagIds']) {
                $photo['tagIds'] = explode(',', $photo['tagIds']);
            } else {
                $photo['tagIds'] = [];
            }

            if (S3::instance()->doesExist($photo['name'], 'gallery')) {
                $url = S3::instance()->getPresignedUrl($photo['name'], 'gallery');
            } else {
                $url = S3::instance()->getPresignedUrl($photo['name'], 'gallery', 'pam-img-raw');
            }

            if (S3::instance()->doesExist($photo['name'] . Directory::THUMBNAIL_SUFFIX, 'gallery')) {
                $thumbnailUrl = S3::instance()->getPresignedUrl($photo['name'] . Directory::THUMBNAIL_SUFFIX, 'gallery');
            } else {
                $thumbnailUrl = $url;
            }

            $photo['url'] = $url;
            $photo['thumbnailUrl'] = $thumbnailUrl;
            $photos[$photo['id']] = $photo;
        }
        return $photos;
    }

    /**
     * @param $filter
     * @param $start
     * @param $count
     * @return string
     */
    private function buildSelectPhotosSqlQuery($filter, $start, $count)
    {
        $query = "SELECT photo.*, users.first_name as firstName, users.last_name as lastName,
GROUP_CONCAT(DISTINCT tag.id order by tag.id) tagIds,
sites.site_name as siteName, sites.site_code as siteCode,
site_buildings.b_name as buildingName, site_buildings.b_code as buildingCode, 
site_levels.l_name as levelName, site_levels.l_code_display as levelCode
FROM `photo`
JOIN `users` on (users.id = photo.userId)
LEFT JOIN `photo_tag` on (photo_tag.photoId = photo.id)
LEFT JOIN `tag` on (tag.id = photo_tag.tagId)
LEFT JOIN `sites` on (sites.site_id = photo.siteId)
LEFT JOIN `site_buildings` on (site_buildings.b_id = photo.buildingId)
LEFT JOIN `site_levels` on (site_levels.l_id = photo.levelId)";

        $whereStatements = [];

        if (array_key_exists('tagIds', $filter) && !empty($filter['tagIds'])) {
            $tagIdsStatements = [];
            if (false !== $nullKey = array_search(null, $filter['tagIds'])) {
                unset($filter['tagIds'][$nullKey]);
                $tagIdsStatements[] = 'tag.id IS null';
            }
            if (!empty($filter['tagIds'])) {
                $tagIdsStatement = Utils::arrayToInStatement($filter['tagIds'], $this->db);
                $tagIdsStatements[] = "tag.id IN ({$tagIdsStatement})";
            }
            $whereStatements[] = '(' . implode(' OR ', $tagIdsStatements) . ')';
        }

        if (array_key_exists('startDate', $filter)) {
            $startTimestamp = strtotime($filter['startDate']);
            if (false !== $startTimestamp) {
                $startDate = date('Y-m-d \0\0:\0\0:\0\0', $startTimestamp);
                $whereStatements[] = "creationTime > '{$startDate}'";
            }
        }
        if (array_key_exists('endDate', $filter)) {
            $endTimestamp = strtotime($filter['endDate']);
            if (false !== $endTimestamp) {
                $endDate = (new \DateTime())->setTimestamp($endTimestamp)->add(
                    new \DateInterval('P1D'))->format('Y-m-d \0\0:\0\0:\0\0'
                );
                $whereStatements[] = "creationTime < '{$endDate}'";
            }
        }
        if (array_key_exists('startDatetime', $filter)) {
            $startDate = $filter['startDatetime']->format('Y-m-d H:i:s');
            $whereStatements[] = "creationTime > '{$startDate}'";
        }
        if (array_key_exists('endDatetime', $filter)) {
            $endDate = $filter['endDatetime']->format('Y-m-d H:i:s');
            $whereStatements[] = "creationTime < '{$endDate}'";
        }
        if (array_key_exists('orderByCreationTimeDESC', $filter) && $filter['orderByCreationTimeDESC'] == true) {
            $orderByCreationTimeKeyword = 'DESC';
        } else {
            $orderByCreationTimeKeyword = 'ASC';
        }
        unset($filter['orderByCreationTimeDESC']);
        unset($filter['startDatetime']);
        unset($filter['endDatetime']);
        unset($filter['startDate']);
        unset($filter['endDate']);
        unset($filter['tagIds']);

        foreach ($filter as $column => $value) {
            if (empty($value)) {
                continue;
            }
            $escapedValue = mysqli_real_escape_string($this->db, $value);
            $whereStatements[] = "`{$column}` = '{$escapedValue}'";
        }
        if (count($whereStatements) > 0) {
            $query .= ' WHERE ' . implode(' AND ', $whereStatements);
        }

        $query .= "GROUP BY photo.id ORDER BY photo.creationTime $orderByCreationTimeKeyword, photo.originalName $orderByCreationTimeKeyword";

        if ($start !== null && $count !== null) {
            $query .= " LIMIT $start, $count";
            return $query;
        }
        return $query;
    }

    private function assignTag($photoId, $tagId) {
        $photoId = mysqli_real_escape_string($this->db, $photoId);
        $tagId = mysqli_real_escape_string($this->db, $tagId);
        $insertQuery = "INSERT INTO `photo_tag` (`photoId`, `tagId`) VALUES ('{$photoId}', '{$tagId}')";
        mysqli_query($this->db, $insertQuery); //TODO: check result
    }

    private function clearTags($photoIds) {
        $photoIdsStatement = Utils::arrayToInStatement($photoIds, $this->db);
        $query = "DELETE from `photo_tag` WHERE `photoId` in ({$photoIdsStatement})";
        mysqli_query($this->db, $query); //TODO: check result
    }
}