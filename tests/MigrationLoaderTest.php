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
use Puff\Migration\MigrationLoader;

final class MigrationLoaderTest extends TestCase
{
    public function testCreatesTableMigrationFromTableStub(): void
    {
        $directory = \sys_get_temp_dir() . '/puff-migration-' . \bin2hex(\random_bytes(6));
        self::assertTrue(\mkdir($directory));
        $loader = new MigrationLoader($directory);

        $path = $loader->create('add_nickname', 'users');
        $source = (string) \file_get_contents($path);
        self::assertStringContainsString('$schema->table(\'users\'', $source);
        self::assertStringContainsString('Blueprint $table', $source);

        \unlink($path);
        \rmdir($directory);
    }
}
