<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/migration
 * https://github.com/php-puff/migration/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Migration;

use Puff\Config\Config;
use Puff\Database\Contract\DatabaseManagerInterface;
use Puff\Di\Container;
use Puff\Di\ServiceProvider as BaseServiceProvider;

final class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        if (!$this->app->bound(Config::class) || !$this->app->bound(DatabaseManagerInterface::class)) {
            return;
        }
        $config = $this->app->make(Config::class)->get('migration', []);
        if (!\is_array($config)) {
            $config = [];
        }
        $directory = (string) ($config['path'] ?? 'database/migrations');
        $this->app->singleton(Migrator::class, static function (Container $container) use ($directory): Migrator {
            $databases = $container->make(DatabaseManagerInterface::class);
            if (!$databases instanceof DatabaseManagerInterface) {
                throw new \RuntimeException('Database manager service is invalid.');
            }
            return new Migrator(
                $databases,
                $directory,
            );
        });
    }
}
