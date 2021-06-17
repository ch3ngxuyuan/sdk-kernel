<?php

namespace SDK\Kernel;

class Signer
{
    public static function sign(array $params): string
    {
        $params = array_filter($params, function ($param) {
            return $param !== null && $param !== '';
        });

        $params = array_map(function ($param) {
            if (is_array($param)) {
                return json_encode($param, JSON_UNESCAPED_UNICODE);
            }
            return $param;
        }, $params);

        ksort($params);

        foreach ($params as $k => $v) {
            $sign[] = "{$k}={$v}";
        }
        $sign = join('&', $sign);

        return strtoupper(md5($sign));
    }

    public static function verify(string $signature, array $params): bool
    {
        return $signature == self::sign($params);
    }
}