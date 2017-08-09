<?php
namespace Pam\Geo;

class Point
{
    public $x;
    public $y;

    /**
     * Point constructor.
     * @param $x
     * @param $y
     */
    public function __construct($x = null, $y = null)
    {
        $this->x = $x;
        $this->y = $y;
    }
}
