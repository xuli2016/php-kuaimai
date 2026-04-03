<?php

namespace Kuaimai\Util;

use Kuaimai\Bean\ResponseEnvelope;

class RequestTool
{
    /**
     * POST JSON request to Kuaimai API endpoint.
     * Equivalent to Java RequestTool.postRequest()
     */
    public static function postRequest(string $url, array $params): ResponseEnvelope
    {
        $body = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=UTF-8'],
        ]);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($errno !== 0) {
            $msg = ($errno === CURLE_COULDNT_RESOLVE_HOST)
                ? '本地网络不通，请检查网络连接'
                : '请求失败: ' . $error;
            return ResponseEnvelope::error($msg);
        }

        $arr = json_decode($response, true);
        if ($arr === null) {
            return ResponseEnvelope::error('响应解析失败: ' . $response);
        }

        return ResponseEnvelope::fromArray($arr);
    }
}
