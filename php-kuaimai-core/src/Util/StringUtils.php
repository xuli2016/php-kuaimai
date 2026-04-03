<?php

namespace Kuaimai\Util;

class StringUtils
{
    public static function isBlank(?string $str): bool
    {
        return $str === null || trim($str) === '';
    }

    public static function isNotBlank(?string $str): bool
    {
        return !self::isBlank($str);
    }
}
