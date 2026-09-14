<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/migration
 * https://github.com/php-puff/migration/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Migration\Tests;

use PHPUnit\Framework\TestCase;
use Puff\Config\Config;
use Puff\Database\Contract\DatabaseManagerInterface;
use Puff\Database\DatabaseManager;
use Puff\Di\Container;
use Puff\Migration\Migrator;
use Puff\Migration\ServiceProvider;

final class ServiceProviderTest extends TestCase
{
    public function testDoesNothingWithoutDatabaseManager(): void
    {
        $container = new Container();
        $container->instance(Config::class, new Config());
        (new ServiceProvider($container))->register();
        self::assertFalse($container->bound(Migrator::class));
    }

    public function testRegistersMigratorFromDatabaseContract(): void
    {
        $container = new Container();
        $container->instance(Config::class, new Config(['migration' => ['path' => 'database/migrations']]));
        $database = new DatabaseManager('sqlite', [
            'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:'],
        ]);
        $container->instance(DatabaseManagerInterface::class, $database);
        (new ServiceProvider($container))->register();

        self::assertTrue($container->bound(Migrator::class));
        self::assertInstanceOf(Migrator::class, $container->make(Migrator::class));
    }
}
