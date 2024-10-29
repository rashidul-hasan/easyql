<?php

namespace Rashidul\EasyQL;

class Util {
    public static function hasNestedArrays(array $array): bool {
        // Check if the array contains nested arrays
        foreach ($array as $item) {
            if (is_array($item)) {
                return true;
            }
        }
        return false;
    }
}