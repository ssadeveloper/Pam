<?php
namespace Pam\Assets;

class Templates
{
    public static function getIntNumber($templateNumber){
        if (!is_numeric($templateNumber)) {
            $alfabet = range('A', 'Z');
            $alfabet = implode('', $alfabet);
            $templateNumber = strpos($alfabet, $templateNumber) + 1;
            if (!$templateNumber) $templateNumber = 1;
        }

        return $templateNumber;
    }
}