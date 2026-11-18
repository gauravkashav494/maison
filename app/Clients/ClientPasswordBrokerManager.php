<?php

namespace App\Clients;

use Illuminate\Auth\Passwords\PasswordBrokerManager;

/**
 * Password brokers whose accounts exist once per client (`'scoped_by_client' => true` in
 * config/auth.php) keep their reset tokens per client too. Every other broker is unchanged.
 */
class ClientPasswordBrokerManager extends PasswordBrokerManager
{
    protected function createTokenRepository(array $config)
    {
        if (! ($config['scoped_by_client'] ?? false)) {
            return parent::createTokenRepository($config);
        }

        $key = $this->app['config']['app.key'];

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return new ClientScopedTokenRepository(
            $this->app['db']->connection($config['connection'] ?? null),
            $this->app['hash'],
            $config['table'],
            $key,
            ($config['expire'] ?? 60) * 60,
            $config['throttle'] ?? 0,
        );
    }
}
