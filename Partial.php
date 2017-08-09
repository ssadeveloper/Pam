<?php
namespace Pam;

class Partial
{
    /**
     * Template variables
     * @var array
     */
    private $__params = [];

    /**
     * Template file path
     * @var
     */
    private $__path;

    /**
     * Partial constructor.
     * @param $path
     * @param array $params
     */
    public function __construct($path, $params = [])
    {
        $this->__path = $path;
        $this->__params = $params;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->__params;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->__params[$name];
    }

    /**
     * @param $path
     * @param $params
     * @param bool $appendParams
     * @return string
     */
    public function partial($path, $params = [], $appendParams = true)
    {
        $partial = new Partial($path, $params + ($appendParams ? $this->__params : []));
        return $partial->process();
    }

    /**
     * @return string
     */
    public function process()
    {
        ob_start();
        extract($this->__params);
        include($this->__path);
        return ob_get_clean();
    }
}