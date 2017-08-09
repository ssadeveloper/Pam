<?php
namespace Pam\Assets;

use Pam\Model;


class ScreenType extends Model
{
    const ORIENTATION = ['landscape' => 'Landscape', 'portrait' => 'Portrait'];

    protected $tableName = 'digital_directory_screen_type';

    protected $idColumn = 'id';

    public static function getDisplayOrientation($orientation)
    {
        return array_key_exists($orientation, static::ORIENTATION) ? static::ORIENTATION[$orientation]: '';
    }
}