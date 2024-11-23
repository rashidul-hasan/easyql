<?php

namespace Rashidul\EasyQL;

class Util {
    public static function hasNestedArrays(array $array): bool {
        // Check if the array contains nested arrays, to bulk insert
        foreach ($array as $item) {
            if (is_array($item)) {
                return true;
            }
        }
        return false;
    }
    
    //if data param in the payload only has array, then we are bulk inserting
    public static function isBulkInsert(array $array): bool {
        // Check if the array contains nested arrays, to bulk insert
        foreach ($array as $item) {
            if (!is_array($item)) {
                return false;
            }
        }
        return true;
    }
}