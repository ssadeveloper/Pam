<?php

namespace Pam\Config;

if (\Pam\Client::isClientInitiated()) {
    Paths::setClientSpecificSubfolder(Paths::getFolderNameForString(\Pam\Client::get()->getId()));
}

/**
 * Class Paths - contains client specific digital assets paths location
 * Usage: Paths::get()->photos returns 'assets/photos'
 * 
 *        To get path to site asset with subfolder, use Paths::get()->getPath('photos, 'subfolder')
 *        It returns 'assets/photos/subfolder'
 * 
 * @package Pam
 * @property string assets
 * @property string installationDiagrams
 * @property string photoDirectory
 * @property string qrCodes
 * @property string signatures
 * @property string templatePreviews
 * @property string css
 * @property string fpdf
 */
class Paths
{
    const DIR_ASSETS = 'assets';

    /**
     * @var array: {pathName} => {pathComponent}[]
     */
    private $paths = [
        'assets' => [self::DIR_ASSETS],
        'installationDiagrams' => [self::DIR_ASSETS, 'installation_diagrams'],
        'photoDirectory' => [self::DIR_ASSETS, 'photo_directory'],
        'qrCodes' => [self::DIR_ASSETS, 'qr_codes'],
        'signatures' => [self::DIR_ASSETS, 'signatures'], //currently it isn't used
        'templatePreviews' => [self::DIR_ASSETS, 'template_previews'],
        'css' => ['css'],
        'fpdf' => ['fpdf'],
    ];

    /**
     * @var string
     */
    private static $clientSpecificSubfolder = '';

    /**
     * @var Paths
     */
    private static $instance;

    private function __construct()
    {}

    /**
     * @return Paths
     */
    public static function get()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * @param $name
     * @param string $subfolder For making path to file with client specific folder
     *  For example, $subfolder = 'pamsite' 
     *  Path to default installation diagrams is 'assets/pamsite/installation_diagrams'
     * 
     * @return null|string
     */
    public function getPath($name, $subfolder = '') {
        if (array_key_exists($name, $this->paths)) {
            $arrayPath = $this->paths[$name];
            if (strlen($subfolder)) {
                array_push($arrayPath, $subfolder);
            }
            return $this->gluePath($arrayPath);
        }
        return null;
    }
    
    public function getPathForCurrentSiteName($name) {
        return $this->getPath($name, static::getClientSpecificSubfolder());
    }

    /**
     * @param string $name
     *
     * @return null|string
     */
    public function __get($name)
    {
        return $this->getPath($name);
    }

    /**
     * @param string[] $path
     * @return string
     */
    private function gluePath($path)
    {
        return implode(DIRECTORY_SEPARATOR, $path);
    }

    /**
     * @param $str
     * @return mixed
     */
    public static function getFolderNameForString($str) {
        return trim(str_replace(array(' ', '..'), array('-', '.'), strtolower($str)), '.');
    }

    /**
     * @return string
     */
    public static function getClientSpecificSubfolder()
    {
        return self::$clientSpecificSubfolder;
    }

    /**
     * @param string $clientSpecificSubfolder
     */
    public static function setClientSpecificSubfolder($clientSpecificSubfolder)
    {
        self::$clientSpecificSubfolder = $clientSpecificSubfolder;
    }
}