<?php

namespace Pam\Utils;

use Pam\Aws\S3;

class Image
{
    /**
     * @param $svg
     * @param null $width
     * @param null $height
     * @return null|string
     * @throws \Exception
     */
    public static function svg2png($svg, $width=null, $height=null)
    {
        $descriptorSpec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ];

        $cwd = '/tmp';
        $cmd = 'svg2png /dev/stdin --output=/dev/stdout';
        if (!is_null($width)) {
            $cmd .= ' --width=' . $width;
        }
        if (!is_null($height)) {
            $cmd .= ' --height=' . $height;
        }
        $process = proc_open($cmd, $descriptorSpec, $pipes, $cwd);

        if (!is_resource($process)) {
            return null;
        }
        fwrite($pipes[0], $svg);
        fclose($pipes[0]);

        $png = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $returnValue = proc_close($process);
        if ($returnValue != 0) {
            throw new \Exception($errors, $returnValue);
        }
        return $png;
    }

    /**
     * Checks if $svg contains svg data
     *
     * @param $svg
     * @return bool
     */
    public static function isSvg($svg)
    {
        return isset($svg[0]) && $svg[0] == '<';
    }
    
    
    public static function rotate(\Imagick $image, $degree) {
        if ($degree % 360 != 0) {
            $image->rotateImage(new \ImagickPixel(), $degree);
        }
        
        return $image;
    }
    
    public static function scale(\Imagick $image, $width, $height) {
        if ($image->getImageWidth() < $image->getImageHeight()) {
            $image->scaleImage(0, $width);
        } else {
            $image->scaleImage($height, 0);
        }
        
        return $image;
    }

    /**
     * @param \Imagick $image
     * @param int $width Width of a thumbnail crop to
     * @param int $height Height of a thumbnail crop to
     * @param string $key Key of a file to save
     * @param string $ext
     */
    public static function cropThumbnailAndSave(\Imagick $image, $width, $height, $key, $ext) {
        $canvas = new \Imagick();
        $canvas->newImage($image->getImageWidth(), $image->getImageHeight(), new \ImagickPixel('white'));
        $canvas->setImageFormat($ext);
        $canvas->compositeImage($image, \Imagick::COMPOSITE_OVER, 0, 0);

        $canvas->cropThumbnailImage($width, $height);
        $thumbnailContent = $canvas->__toString();
        S3::instance()->save($thumbnailContent, $ext, $key);
    }
}