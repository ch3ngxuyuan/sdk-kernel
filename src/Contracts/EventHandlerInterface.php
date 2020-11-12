<?php

namespace SDK\Kernel\Contracts;

interface EventHandlerInterface
{
    /**
     * @param mixed $payload
     */
    public function handle($payload = null);
}