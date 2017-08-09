<?php
namespace Pam\Migration;

class Helper {

    /**
     * @param string[] $input
     * @param string[] $expectedParams
     * @return array
     */
    public static function parseInputParams($input, $expectedParams)
    {
        $result = [];
        foreach ($input as $key => $arg) {
            if (0 !== strpos($arg, '--')) {
                continue;
            }
            $option = substr($arg, 2);
            if (false !== $pos = strpos($option, '=')) {
                $optionName = substr($option, 0, $pos);
                $optionValue = substr($option, $pos + 1);
            } else {
                $optionName = $option;
                $optionValue = isset($input[$key + 1]) ? $input[$key + 1] : '';
            }
            if (array_key_exists($optionName, $expectedParams)) {
                $result[$optionName] = $optionValue;
            }
        }
        return $result;
    }
}