<?php
namespace Pam\Geo;

use Aws\CommandPool;
use Pam\Aws\S3;
use Pam\Log;
use Pam\Utils\Upload;

class MapTiles
{
    const MAP_TILES_FOLDER = 'map-tiles';
    
    const TILES_PRESIGN_URLS_X_RANGE = 5;

    /**
     * @var MapTiles
     */
    private static $instance;

    /**
     * @return MapTiles
     */
    public static function get()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function __construct()
    {}

    /**
     * Upload MBTiles file content to S3 bucket.
     * As a result the following file structure is created in S3 bucket:
     * - <client> (e.g. ddtest1)
     *  - map-tiles
     *   - <level-UID1>
     *    - metadata.json
     *    - <zoom-level1>
     *     - <x1>
     *      - <y1>.png
     *      ...
     *      - <yN>.png
     *      ...
     *     - <xM>
     *     ...
     *     - <zoom-levelK>
     *     ...
     *    ...
     *   - <level-UIDJ>
     * @param string $s3mbtilesFileName
     * @param string $levelUid
     * @param bool $isZip
     *
     * @throws \Exception
     */
    public function processMbtiles($s3mbtilesFileName, $levelUid, $isZip = true) {
        $S3Instance = S3::instance();
        $S3Instance->registerStreamWrapper();
        if ($s3Stream = fopen($S3Instance->getStreamName($s3mbtilesFileName, self::MAP_TILES_FOLDER), 'r')) {
            $filePath = tempnam('/tmp/', 'mtiles-zip');
            $fileStream = fopen($filePath, 'w');
            while (!feof($s3Stream)) {
                fwrite($fileStream, fread($s3Stream, 1048576));
            }
            fclose($s3Stream);
            fclose($fileStream);
            $S3Instance->delete($s3mbtilesFileName, self::MAP_TILES_FOLDER);

            if ($isZip) {
                $zipArchive = new \ZipArchive();
                $result = $zipArchive->open($filePath);
                $this->checkZipArchiveOpenResult($result);

                $tmpTilesDir = '/tmp/mtiles' . microtime(true) . DIRECTORY_SEPARATOR;
                mkdir($tmpTilesDir);
                $zipArchive->extractTo($tmpTilesDir);
                $zipArchive->close();

                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tmpTilesDir));
                $maptileFiles = [];
                foreach ($iterator as $file) {
                    if ($file->isDir()){
                        continue;
                    }

                    $maptileFiles[] = $file->getPathname();
                }
                sort($maptileFiles);
                $metadata = $this->generateMbtilesMetadataFromFiles($maptileFiles);
                $this->uploadMbtilesMetadataToS3($metadata, $levelUid);

                $commands = $this->tileUploadCommandGeneratorForFiles($maptileFiles, $levelUid);
            } else {
                $sqliteConnection = new \PDO("sqlite:$filePath");

                $metadata = $this->extractMbtilesMetadata($sqliteConnection);
                $this->uploadMbtilesMetadataToS3($metadata, $levelUid);

                $queryResult = $sqliteConnection->query("SELECT * FROM tiles");
                $this->checkSqliteQueryResult($sqliteConnection, $queryResult);
                $commands = $this->tileUploadCommandGenerator($queryResult, $metadata, $levelUid);
            }
            $pool = new CommandPool(S3::instance()->getClient(), $commands);
            $pool->promise()->wait();
            unlink($filePath);
            if ($isZip && isset($tmpTilesDir)) {
                Upload::deleteDir($tmpTilesDir);
            }
        } else {
            throw new \Exception("Can't read uploaded file");
        }
    }

    /**
     * @param $result
     * @throws Exception
     */
    private function checkZipArchiveOpenResult($result)
    {
        if (true === $result) {
            return;
        }
        $message = 'Unable to open map tiles zip archive file';
        Log::get()->addError($message);
        throw new Exception($message);
    }

    /**
     * Get tiles metadata for specified level
     * @param string $levelUid
     * @return mixed
     * @throws \Exception
     */
    public function getMetadata($levelUid) {
        $metadataPath = static::MAP_TILES_FOLDER . "/{$levelUid}/metadata.json";
        try {
            $metadataJson = S3::instance()->getFile($metadataPath, '', '', true);
        } catch (\Exception $e) {
            Log::get()->addError('Unable to load map tiles metadata for level ' . $levelUid . ': ' . $e->getMessage());
            throw $e;
        }
        return json_decode($metadataJson, true);
    }

    /**
     * Get tile presigned URL of tile on S3
     * @param string $levelUid
     * @param int $zoomLevel
     * @param int $x
     * @param int $y
     * @return string
     */
    public function getTileUrl($levelUid, $zoomLevel, $x, $y) {
        $ext = 'png';
        $tilePath = "map-tiles/{$levelUid}/{$zoomLevel}/{$x}/{$y}.{$ext}";
        return S3::instance()->getPresignedUrl($tilePath);
    }

    /**
     * @param string $levelUid
     * @return bool
     */
    public function isMapTilesExist($levelUid) {
        return !empty($levelUid);
    }

    /**
     * @param \PDO $sqliteConnection
     * @return array
     */
    private function extractMbtilesMetadata(\PDO $sqliteConnection)
    {
        $metadata = [];
        $result = $sqliteConnection->query("SELECT * FROM metadata");
        $this->checkSqliteQueryResult($sqliteConnection, $result);
        foreach ($result as $metadataRow) {
            $metadata[$metadataRow['name']] = $metadataRow['value'];
        }
        if (!array_key_exists('format', $metadata)) {
            $result = $sqliteConnection->query('SELECT hex(substr(tile_data,1,2)) AS magic FROM tiles limit 1');
            $this->checkSqliteQueryResult($sqliteConnection, $result);
            $resultData = $result->fetchAll();
            $metadata['format'] = ($resultData[0]['magic'] == 'FFD8') ? 'jpg' : 'png';
        }
        if (array_key_exists('bounds', $metadata)) {
            $mapBounds = explode(',', $metadata['bounds']);
            $metadata['bounds'] = $mapBounds;
            return $metadata;
        }
        return $metadata;
    }

    /**
     * @param \PDO $sqliteConnection
     * @param \PDOStatement|false $result
     * @throws Exception
     */
    private function checkSqliteQueryResult($sqliteConnection, $result)
    {
        if (false !== $result) {
            return;
        }
        $errorInfo = $sqliteConnection->errorInfo();
        if (isset($errorInfo[2])) {
            $errorMessage = $errorInfo[2];
        } else {
            $errorMessage = 'unknown error';
        }
        $message = "Unable to fetch data from map tiles file: {$errorMessage}";
        Log::get()->addError($message);
        throw new Exception($message);
    }

    /**
     * @param array $files list of map tile filenames sorted in ascending order
     * @return array
     */
    private function generateMbtilesMetadataFromFiles($files)
    {
        $metadata = [];
        $minTileColumn = null;
        $maxTileColumn = null;
        $minTileRow = null;
        $maxTileRow = null;
        $minZoom = null;
        $maxZoom = null;

        for ($i = 0; $i < count($files); $i++) {
            $pathExploded = explode('/', $files[$i]);
            if (count($pathExploded) < 7) {
                continue;
            }
            list(,,,, $tileZoom, $tileX, $tileFileName) = $pathExploded;
            list($tileY) = explode('.', $tileFileName);

            if ($tileZoom < $maxZoom) {
                continue;
            } elseif ($tileZoom > $maxZoom) {
                $maxZoom = $tileZoom;
                $minTileColumn = null;
                $maxTileColumn = null;
                $minTileRow = null;
                $maxTileRow = null;
            }

            $minZoom = $this->getExtremumValue($minZoom, $tileZoom, false);
            $minTileColumn = $this->getExtremumValue($minTileColumn, $tileX, false);
            $maxTileColumn = $this->getExtremumValue($maxTileColumn, $tileX, true);
            $minTileRow = $this->getExtremumValue($minTileRow, $tileY, false);
            $maxTileRow = $this->getExtremumValue($maxTileRow, $tileY, true);
        }

        $w = -180 + 360 * ($minTileColumn / pow(2, $maxZoom));
        $e = -180 + 360 * ((1 + $maxTileColumn) / pow(2, $maxZoom));

        $maxTileRow = pow(2, $maxZoom) - 1 - (int)$maxTileRow;
        $minTileRow = pow(2, $maxZoom) - 1 - (int)$minTileRow;

        $s = $this->row2lat($maxTileRow, $maxZoom);
        $n = $this->row2lat($minTileRow - 1, $maxZoom);
        $metadata['bounds'] = [$w, $s, $e, $n];
        $metadata['format'] = 'png';
        $metadata['minzoom'] = $minZoom;
        $metadata['maxzoom'] = $maxZoom;
        return $metadata;
    }

    /**
     * Returns max($var, $value) if $getMax == true or min($var, $value) if $getMax == false
     * @param mixed $var
     * @param mixed $value
     * @param bool $getMax
     * @return mixed
     */
    private function getExtremumValue($var, $value, $getMax) {
        if (is_null($var)) {
            return $value;
        } else if (($getMax && $value > $var) || (!$getMax && $value < $var)) {
            return $value;
        } else {
            return $var;
        }
    }

    /**
     * Convert row number to latitude of the top of the row
     * @param integer $r
     * @param integer $zoom
     * @return integer
     */
    private function row2lat($r, $zoom) {
        $y = $r / pow(2, $zoom - 1) - 1;
        return rad2deg(2.0 * atan(exp(M_PI * $y)) - M_PI_2);
    }

    /**
     * @param string $levelUid
     * @param array $metadata
     * @internal param $sqliteConnection
     */
    private function uploadMbtilesMetadataToS3($metadata, $levelUid)
    {
        $metadataJson = json_encode($metadata, JSON_PRETTY_PRINT);
        $metadataFolder = static::MAP_TILES_FOLDER . '/' . $levelUid;
        $result = S3::instance()->save($metadataJson, 'json', 'metadata.json', $metadataFolder);
        $this->checkS3UploadResult($result);
    }

    /**
     * @param $result
     * @throws Exception
     */
    private function checkS3UploadResult($result)
    {
        if (is_null($result)) {
            $message = 'Unable to upload map tile to S3 bucket';
            Log::get()->addError($message);
            throw new Exception($message);
        }
    }

    /**
     * @param \PDOStatement $tileRows
     * @param array $metadata
     * @param string $levelUid
     * @return \Generator
     */
    private function tileUploadCommandGenerator(\PDOStatement $tileRows, $metadata, $levelUid)
    {
        foreach ($tileRows as $tileRow) {
            $tileZoom = $tileRow['zoom_level'];
            $tileX = $tileRow['tile_column'];
            $tileY = pow(2, $tileZoom) - 1 - $tileRow['tile_row'];
            $tileImage = $tileRow['tile_data'];
            $ext = $metadata['format'];
            $tileFileName = "{$tileY}.{$ext}";
            $tileFolder = static::MAP_TILES_FOLDER . "/{$levelUid}/{$tileZoom}/{$tileX}";
            // Yield a command that will be executed by the command pool
            yield S3::instance()->getClient()->getCommand('PutObject', [
                'Bucket' => S3::getBucket(),
                'Key'    => S3::instance()->getKey($tileFileName, $tileFolder),
                'Body'   => $tileImage,
                'ContentType' => "image/{$ext}"
            ]);
        }
    }

    /**
     * @param array $files
     * @param string $levelUid
     * @return \Generator
     */
    private function tileUploadCommandGeneratorForFiles(array $files, $levelUid)
    {
        for ($i = 0; $i < count($files); $i++) {
            list(,,,, $tileZoom, $tileX, $tileFileName) = explode('/', $files[$i]);
            list($tileY, $ext) = explode('.', $tileFileName);

            $tileFileName = "{$tileY}.{$ext}";
            $tileFolder = static::MAP_TILES_FOLDER . "/{$levelUid}/{$tileZoom}/{$tileX}";

            yield S3::instance()->getClient()->getCommand('PutObject', [
                'Bucket' => S3::getBucket(),
                'Key'    => S3::instance()->getKey($tileFileName, $tileFolder),
                'SourceFile'   => $files[$i],
                'ContentType' => "image/{$ext}"
            ]);
        }
    }
    
    public function getPresignedUrlsInBulk($levelUid, $tileZoom, $tileX) {
        $objects = S3::instance()->listObjectsIterator(static::MAP_TILES_FOLDER . "/{$levelUid}/{$tileZoom}/{$tileX}");
        $array = [];
        foreach(S3::instance()->getPresignedUrlsInBulkGenerator($objects) as $file) {
            list(,,,, $tileX, $tileFileName) = explode('/', $file['key']);
            list($tileY) = explode('.', $tileFileName);
            $array["{$tileZoom}_{$tileX}_{$tileY}"] = $file['url'];
        }

        return $array;
    }
    
    public function getPresignedUrlsForArea($levelUid, $tileZoom, $tileX) {
        $array = [];
        for($i = $tileX - self::TILES_PRESIGN_URLS_X_RANGE; $i <= $tileX + self::TILES_PRESIGN_URLS_X_RANGE; $i++) {
            $array += $this->getPresignedUrlsInBulk($levelUid, $tileZoom, $i);
        }
        
        return $array;
    }
}

