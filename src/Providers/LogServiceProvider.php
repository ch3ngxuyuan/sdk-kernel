<?php

namespace SDK\Kernel\Providers;

use SDK\Kernel\Log\LogManager;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class LogServiceProvider implements ServiceProviderInterface
{
    public function register(Container $pimple)
    {
        !isset($pimple['log']) && $pimple['log'] = function ($app) {
            if (empty($config = $app['config']->get('log'))) {
                return null;
            }

            $app->rebind('config', $app['config']->merge($config));

            return new LogManager($app);
        };

        !isset($pimple['logger']) && $pimple['logger'] = $pimple['log'];
    }
}