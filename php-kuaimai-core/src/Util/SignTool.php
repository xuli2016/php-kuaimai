<?php

namespace Kuaimai\Util;

class SignTool
{
    private static function stringifyValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $json === false ? null : $json;
        }

        return (string)$value;
    }

    /**
     * Create MD5 signature identical to Java SignTool.createSign()
     *
     * Algorithm:
     * 1. Filter: skip params where key or value is blank
     * 2. Sort keys alphabetically
     * 3. Concatenate: secret + k1v1k2v2... + secret
     * 4. MD5 → lowercase hex
     */
    public static function createSign(array $params, string $secret): string
    {
        // Filter blank keys/values
        $filtered = [];
        foreach ($params as $k => $v) {
            $kStr = (string)$k;
            $vStr = self::stringifyValue($v) ?? '';
            if (StringUtils::isBlank($kStr) || StringUtils::isBlank($vStr)) {
                continue;
            }
            $filtered[$kStr] = $vStr;
        }

        // Sort by key
        ksort($filtered);

        // Build string: secret + sorted(k+v pairs) + secret
        $sb = $secret;
        foreach ($filtered as $k => $v) {
            $sb .= $k . $v;
        }
        $sb .= $secret;

        return md5($sb);
    }
}
