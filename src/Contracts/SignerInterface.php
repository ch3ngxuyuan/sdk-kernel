<?php

namespace SDK\Kernel\Contracts;

/**
 * Interface AuthorizerSigner.
 */
interface SignerInterface
{
    /**
     * @param array $params
     *
     * @return string
     */
    public function sign(array $params): string;

    /**
     * @param string $signature
     * @param array $params
     *
     * @return bool
     */
    public function verify(string $signature, array $params): bool;
}