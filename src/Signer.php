<?php

namespace SDK\Kernel;

use SDK\Kernel\Contracts\SignerInterface;

abstract class Signer implements SignerInterface
{
    /**
     * @var \SDK\Kernel\ServiceContainer
     */
    protected $app;

    /**
     * AccessToken constructor.
     *
     * @param \SDK\Kernel\ServiceContainer $app
     */
    public function __construct(ServiceContainer $app)
    {
        $this->app = $app;
    }
    
    /**
     * @param array $params
     *
     * @return string
     */
    public function sign(array $params): string
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

    /**
     * @param string $signature
     * @param array $params
     *
     * @return bool
     */
    public function verify(string $signature, array $params): bool
    {
        return $signature == $this->sign($params);
    }
}