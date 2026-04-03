<?php

namespace Kuaimai\Util;

class ZLibUtils
{
    /**
     * ZLib deflate compress (equivalent to Java ZLibUtils.compress)
     */
    public static function compress(string $data): string
    {
        $compressed = zlib_encode($data, ZLIB_ENCODING_DEFLATE);
        if ($compressed === false) {
            throw new \RuntimeException('ZLib compress failed');
        }
        return $compressed;
    }

    /**
     * ZLib inflate decompress (equivalent to Java ZLibUtils.decompress)
     */
    public static function decompress(string $data): string
    {
        $decompressed = zlib_decode($data);
        if ($decompressed === false) {
            throw new \RuntimeException('ZLib decompress failed');
        }
        return $decompressed;
    }
}
