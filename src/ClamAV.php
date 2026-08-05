<?php

declare(strict_types=1);

namespace Kipchak\Driver\ClamAV;

use Kipchak\Core\Interfaces;
use Kipchak\Driver\Config\Config;
use Psr\Container\ContainerInterface;

/**
 * ClamAV driver. Registers one clamd Client per named connection from the
 * `kipchak.clamav` config, resolved via ClamAV::get('<name>').
 */
class ClamAV implements Interfaces\Driver
{
    protected static ?ContainerInterface $container = null;

    public static function initialise(ContainerInterface $container, ?string $configFileName = null): void
    {
        self::$container = $container;
        self::set($configFileName);
    }

    public static function set(?string $configFileName = null): void
    {
        $configFileName = $configFileName ?? 'kipchak.clamav';
        $connections = Config::get($configFileName);
        if (empty($connections) || !is_array($connections)) {
            return;
        }

        foreach ($connections as $name => $connectionDetails) {
            if (!is_array($connectionDetails)) {
                continue;
            }
            self::$container->set('drivers.clamav.' . $name, function (ContainerInterface $c) use ($connectionDetails): Client {
                return new Client(
                    (string) ($connectionDetails['host'] ?? '127.0.0.1'),
                    (int) ($connectionDetails['port'] ?? 3310),
                    (float) ($connectionDetails['timeout'] ?? 30.0),
                );
            });
        }
    }

    public static function get(?string $configKey = null): Client
    {
        return self::$container->get('drivers.clamav.' . ($configKey ?? 'default'));
    }
}
