<?php

namespace Pam\Photo;

use Pam\Db\Utils;
use Pam\Assets\Assets;
use Pam\Aws\S3;

class Directory {
    const THUMBNAIL_SUFFIX = '_200';
    const THUMBNAIL_WIDTH = 200;
    const THUMBNAIL_HEIGHT = 200;
    const IMAGE_WIDTH = 1920;

    private static $SUPPORTED_FILE_TYPES;

    /**
     * @var Directory
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
     * @return Directory
     */
    public static function get()
    {
        if (!static::$instance) {
            static::$SUPPORTED_FILE_TYPES = ['png', 'jpg', 'jpeg'];
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * @param string $originalFileName
     * @param string $tmpName
     * @param $location
     * @param array $filesCreationDatetime Associative array filename => ms
     * @return array
     * @throws \Exception
     */
    public function upload($originalFileName, $tmpName, $location, $filesCreationDatetime = []) {
        $tmp = explode('.', $originalFileName);
        $ext = strtolower(end($tmp));
        $filesize = filesize($tmpName);

        if (!in_array($ext, static::$SUPPORTED_FILE_TYPES)) {
            throw new \Exception('The image must be in PNG or JPG format, but ' . strtoupper($ext) . ' is uploaded');
        }

        $uploadedImage = new \Imagick($tmpName);

        $imageHeight = $uploadedImage->getImageHeight();
        $imageWidth = $uploadedImage->getImageWidth();
        $creationTime = $this->extractCreationTimeFromPhoto($uploadedImage);
        if (!$creationTime && $filesCreationDatetime && array_key_exists($originalFileName, $filesCreationDatetime)) {
            $creationTime = date("Y-m-d H:i:s", floor($filesCreationDatetime[$originalFileName] / 1000));
        }

        $canvas = new \Imagick();
        $canvas->newImage($imageWidth, $imageHeight, new \ImagickPixel('white'));
        $canvas->setImageFormat($ext);
        $canvas->compositeImage($uploadedImage, \Imagick::COMPOSITE_OVER, 0, 0);

        if ($imageWidth < $imageHeight) {
            $canvas->scaleImage(0, static::IMAGE_WIDTH);
        } else {
            $canvas->scaleImage(static::IMAGE_WIDTH, 0);
        }
        $photoContent = $canvas->__toString();
        $fileName = S3::instance()->save($photoContent, $ext, null, 'gallery');

        $canvas->cropThumbnailImage(static::THUMBNAIL_HEIGHT, static::THUMBNAIL_WIDTH);
        $thumbnailContent = $canvas->__toString();
        S3::instance()->save($thumbnailContent, $ext, $fileName . static::THUMBNAIL_SUFFIX, 'gallery');

        $photoId = $this->saveToDb($originalFileName, $fileName, $ext, $creationTime, $location);
        $result = $this->getSuccessUploadResult($photoId, $originalFileName, $fileName, $ext);
        $result['size'] = $filesize;
        return $result;
    }

    public function processRawImage($photo)
    {
        try {
            $file = S3::instance()->getFile($photo['filename'], 'gallery', S3::$rawImageFolder);
            if ($file == null) {
                return false;
            }
            $image = new \Imagick();
            $image->readImageBlob($file);
            $imageHeight = $image->getImageHeight();
            $imageWidth = $image->getImageWidth();

            $canvas = new \Imagick();
            $canvas->newImage($imageWidth, $imageHeight, new \ImagickPixel('white'));
            $canvas->setImageFormat($photo['ext']);
            $canvas->compositeImage($image, \Imagick::COMPOSITE_OVER, 0, 0);

            if ($imageWidth < $imageHeight) {
                $canvas->scaleImage(0, static::IMAGE_WIDTH);
            } else {
                $canvas->scaleImage(static::IMAGE_WIDTH, 0);
            }
            $photoContent = $canvas->__toString();
            S3::instance()->save($photoContent, $photo['ext'], $photo['filename'], 'gallery');

            $canvas->cropThumbnailImage(static::THUMBNAIL_WIDTH, static::THUMBNAIL_HEIGHT);
            $thumbnailContent = $canvas->__toString();
            S3::instance()->save($thumbnailContent, $photo['ext'], $photo['filename'] . static::THUMBNAIL_SUFFIX, 'gallery');
        } catch (\Exception $e) {
            return false;
        }

        return $photoContent;
    }

    /**
     * @param array $ids
     * @param int $limit
     * @param bool $withUrl
     * @return array
     */
    public function getPhotos($ids = null, $limit = null, $withUrl = false)
    {
        $query = 'SELECT * from `photo`';

        if (is_array($ids) && count($ids) > 0) {
            $photoIds = Utils::arrayToInStatement($ids, $this->db);
            $query .= " WHERE `id` IN ({$photoIds})";
        }

        $query .= ' ORDER BY creationTime';

        if (!is_null($limit)) {
            $query .= " LIMIT {$limit}";
        }

        $result = mysqli_query($this->db, $query);
        $photos = [];
        while($photo = mysqli_fetch_assoc($result)) {
            if ($withUrl) {
                $photo['url'] = S3::instance()->getPresignedUrl($photo['name'], 'gallery');
                $photo['thumbnailUrl'] = S3::instance()->getPresignedUrl($photo['name'] . static::THUMBNAIL_SUFFIX, 'gallery');
            }
            $photos[] = $photo;
        }
        return $photos;
    }

    /**
     * @param null $ids
     * @param null $limit
     * @return array
     */
    public function getPhotosWithTags($ids = null, $limit = null)
    {
        $query = "SELECT `photo`.*, tag.name as tagName, tag.id as tagId
                  FROM `photo` 
                  LEFT JOIN `photo_tag` on (photo_tag.photoId = photo.id)
                  LEFT JOIN `tag` on (tag.id = photo_tag.tagId)";

        if (is_array($ids) && count($ids) > 0) {
            $photoIds = Utils::arrayToInStatement($ids, $this->db);
            $query .= " WHERE `photo`.`id` IN ({$photoIds})";
        }

        $query .= ' ORDER BY `photo`.creationTime';

        if (!is_null($limit)) {
            $query .= " LIMIT {$limit}";
        }

        $result = mysqli_query($this->db, $query);
        $photos = [];
        while($photo = mysqli_fetch_assoc($result)){
            $photoId = $photo['id'];
            $tagId = $photo['tagId'];
            $tagName = $photo['tagName'];
            unset($photo['tagId']);
            unset($photo['tagName']);
            
            $photo['url'] = $this->getPresignedUrl($photo['name']);
            $photo['thumbnailUrl'] = $this->getPresignedThumbnailUrl($photo['name'], $photo['url']);
            if (!array_key_exists($photoId, $photos)) {
                $photos[$photoId] = $photo;
            }
            if (!array_key_exists('tags', $photos[$photoId])) {
                $photos[$photoId]['tags'] = [];
            }
            if ($tagId) {
                $photos[$photoId]['tags'][$tagId] = $tagName;
            }
        }
        return $photos;
    }

    /**
     * @param int[] $ids
     * @return bool
     */
    public function removePhotos($ids)
    {
        if (!is_array($ids) || empty($ids)) {
            return true;
        }
        $photoIds = Utils::arrayToInStatement($ids, $this->db);

        $photos = $this->getPhotos($ids);
        foreach ($photos as $photo) {
            if (S3::instance()->doesExist($photo['name'], 'gallery')) {
                S3::instance()->delete($photo['name'], 'gallery');
                S3::instance()->delete($photo['name'] . static::THUMBNAIL_SUFFIX, 'gallery');
            }
        }

        $query = "DELETE from `photo` WHERE `id` in ({$photoIds})";
        $removePhotoResult = mysqli_query($this->db, $query);
        $query = "DELETE FROM `photo_asset` WHERE `photoId` in ({$photoIds})";
        return $removePhotoResult && mysqli_query($this->db, $query);
    }

    /**
     * @param int[] $ids
     * @param int $siteId
     * @param int $buildingId
     * @param int $levelId
     * @return bool|\mysqli_result
     */
    public function assignPhotos($ids, $siteId, $buildingId, $levelId)
    {
        if (!is_array($ids) || empty($ids)) {
            return true;
        }
        $photoIds = Utils::arrayToInStatement($ids, $this->db);
        $siteId = mysqli_real_escape_string($this->db, empty($siteId) ? null : $siteId);
        $buildingId = mysqli_real_escape_string($this->db, empty($buildingId) ? null : $buildingId);
        $levelId = mysqli_real_escape_string($this->db, empty($levelId) ? null : $levelId);

        $query = "UPDATE `photo` SET `siteId`='{$siteId}', `buildingId`='{$buildingId}', `levelId`='{$levelId}' WHERE `id` in ({$photoIds})";
        return mysqli_query($this->db, $query);
    }
    
    public function assignPhotosToAsset($assetId, $photoIds) {
        $photos = $this->getPhotosWithTags($photoIds);
        $files = [];
        foreach($photos as $photo) {
            $files []= [
                'photoId' => $photo['id'],
                'filename' => $photo['name'],
                'ext' => $photo['ext'],
                'isLocationDiagram' => Tags::isLocationDiagram($photo['tags'])];
        }
        return Assets::assignPhotosFromGallery($assetId, $files);
    }
    
    private function getContentType($ext) {
        return 'image/' . $ext;
    }

    /**
     * @param string $originalFileName
     * @param string $fileName
     * @param string $ext
     * @return array
     */
    public function getSuccessUploadResult($photoId, $originalFileName, $fileName, $ext)
    {
        $url = $this->getPresignedUrl($fileName);
        $result = [
            'id' => $photoId,
            'url' => $url,
            'thumbnailUrl' => $this->getPresignedThumbnailUrl($fileName, $url),
            'name' => $originalFileName,
            'ext' => $ext,
            'hashName' => $fileName,
            'type' => $this->getContentType($ext),
            'deleteUrl' => '',
            'deleteType' => 'DELETE'
        ];
        return $result;
    }

    /**
     * @param string $originalFileName
     * @param string $fileName
     * @param string $ext
     * @param string $creationTime
     * @param array $location
     * @return int|string
     */
    public function saveToDb($originalFileName, $fileName, $ext, $creationTime, $location)
    {
        $originalName = mysqli_real_escape_string($this->db, $originalFileName);
        $name = mysqli_real_escape_string($this->db, $fileName);
        $extension = mysqli_real_escape_string($this->db, $ext);
        $uploadTime = mysqli_real_escape_string($this->db, date("Y-m-d H:i:s"));
        $userId = mysqli_real_escape_string($this->db, $_SESSION['USER']['id']);
        if (!$creationTime) {
            $creationTime = date("Y-m-d H:i:s");
        }
        $creationTime = mysqli_real_escape_string($this->db, $creationTime);

        $siteId = mysqli_real_escape_string($this->db, $location['siteId']);
        $buildingId = mysqli_real_escape_string($this->db, $location['buildingId']);
        $levelId = mysqli_real_escape_string($this->db, $location['levelId']);

        $query = "INSERT INTO `photo` (`originalName`, `name`, `ext`, `uploadTime`, `creationTime`, `userId`, `siteId`, `buildingId`, `levelId`) VALUES 
                    ('{$originalName}', '{$name}', '{$extension}', '{$uploadTime}', '{$creationTime}', '{$userId}', '{$siteId}', '{$buildingId}', '{$levelId}')";
        $result = mysqli_query($this->db, $query);
        if (!$result) {
            return null;
        }
        return mysqli_insert_id($this->db);
    }

    /**
     * @param string $hash
     * @return array|null
     */
    private function getPhotoByHash($hash)
    {
        $hash = mysqli_real_escape_string($this->db, $hash);
        $query = "SELECT * FROM `photo` WHERE name = '{$hash}'";
        $result = mysqli_query($this->db, $query);
        return mysqli_fetch_assoc($result);
    }

    /**
     * @param \Imagick $image
     * @return string|null
     */
    private function extractCreationTimeFromPhoto(\Imagick $image)
    {
        $exifData = $image->getImageProperties("exif:*");
        $creationTime = null;
        if (array_key_exists('exif:DateTimeOriginal', $exifData) && strtotime($exifData['exif:DateTimeOriginal']) > 0) {
            $creationTime = date("Y-m-d H:i:s", strtotime($exifData['exif:DateTimeOriginal']));
        }
        return $creationTime;
    }
    
    private function getPresignedUrl($fileName) {
        if (S3::instance()->doesExist($fileName, 'gallery')) {
            $url = S3::instance()->getPresignedUrl($fileName, 'gallery');
        } else {
            $url = S3::instance()->getPresignedUrl($fileName, 'gallery', 'pam-img-raw');
        }
        
        return $url;
    }
    
    private function getPresignedThumbnailUrl($fileName, $defaultUrl) {
        if (S3::instance()->doesExist($fileName . static::THUMBNAIL_SUFFIX, 'gallery')) {
            $url = S3::instance()->getPresignedUrl($fileName . static::THUMBNAIL_SUFFIX, 'gallery');
        } else {
            $url = $defaultUrl;
        }
        return $url;
    }

    public function getPhoto($id) {
        $id = mysqli_real_escape_string($this->db, $id);
        $query = "SELECT * FROM `photo` WHERE id = '{$id}'";
        $result = mysqli_query($this->db, $query);
        return mysqli_fetch_assoc($result);
    }
}