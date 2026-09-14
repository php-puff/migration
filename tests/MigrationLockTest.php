<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/migration
 * https://github.com/php-puff/migration/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Migration\Tests;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Puff\Database\DatabaseManager;
use Puff\Migration\Exception\MigrationException;
use Puff\Migration\MigrationLock;

#[RequiresPhpExtension('pdo_sqlite')]
final class MigrationLockTest extends TestCase
{
    public function testPreventsConcurrentMigrationForSameDatabase(): void
    {
        $database = \tempnam(\sys_get_temp_dir(), 'puff-lock-');
        self::assertIsString($database);
        $manager = new DatabaseManager('sqlite', [
            'sqlite' => ['driver' => 'sqlite', 'database' => $database],
        ]);
        $first = new MigrationLock($manager->connection());
        $second = new MigrationLock($manager->connection());
        try {
            $first->acquire();
            $this->expectException(MigrationException::class);
            $second->acquire();
        } finally {
            $second->release();
            $first->release();
            $manager->disconnectAll();
            @\unlink($database);
        }
    }
}
