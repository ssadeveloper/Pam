<?php

namespace Pam\View;

use Pam\Client;

class View
{
    /**
     * Return filename with full path with checking of subfolder by clientId
     * 
     * @param $controller
     * @param $view
     * @return null|string
     */
    public static function getFileName($controller, $view)
    {
        $path = $_SERVER["DOCUMENT_ROOT"] . '/inc/view/' . $controller . '/';
        $clientId = Client::get()->getId();
        if (is_dir($path . $clientId)) {
            $filename = $path . $clientId . '/' . $view . '.php';
        } else {
            $filename = $path . '/' . $view . '.php';
        }

        return is_file($filename) ? $filename : null;
    }
}
