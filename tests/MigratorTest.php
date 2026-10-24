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
use Puff\Migration\Migrator;

#[RequiresPhpExtension('pdo_sqlite')]
final class MigratorTest extends TestCase
{
    public function testCreatesRunsReportsAndRollsBackMigration(): void
    {
        $database = \tempnam(\sys_get_temp_dir(), 'puff-db-');
        self::assertIsString($database);
        $directory = $database . '-migrations';
        self::assertTrue(\mkdir($directory));
        try {
            $manager = new DatabaseManager('sqlite', [
                'sqlite' => ['driver' => 'sqlite', 'database' => $database, 'prefix' => 'app_'],
            ]);
            $migrator = new Migrator($manager, $directory);
            $path = $migrator->create('create_users_table');
            $source = \file_get_contents($path);
            self::assertIsString($source);
            $source = \str_replace(
                '// Apply create_users_table.',
                '$schema->create(\'users\', static function (\\Puff\\Migration\\Blueprint $table): void { $table->id(); });',
                $source,
            );
            $source = \str_replace('// Revert create_users_table.', '$schema->drop(\'users\');', $source);
            self::assertNotFalse(\file_put_contents($path, $source));

            self::assertSame('pending', $migrator->status()[0]['status']);
            self::assertCount(1, $migrator->run()['migrations']);
            $tables = $manager->connection()->fetchAll("SELECT name FROM sqlite_master WHERE type = 'table'");
            self::assertContains(['name' => 'app_users'], $tables);
            self::assertContains(['name' => 'app_puff_migrations'], $tables);
            self::assertSame('applied', $migrator->status()[0]['status']);
            self::assertCount(1, $migrator->rollback()['migrations']);
            self::assertSame('pending', $migrator->status()[0]['status']);
        } finally {
            foreach (\glob($directory . '/*') ?: [] as $file) {
                \unlink($file);
            }
            \rmdir($directory);
            \unlink($database);
        }
    }

    public function testDetectsModifiedAppliedMigration(): void
    {
        $database = \tempnam(\sys_get_temp_dir(), 'puff-db-');
        self::assertIsString($database);
        $directory = $database . '-migrations';
        self::assertTrue(\mkdir($directory));
        try {
            $migrator = new Migrator(new DatabaseManager('sqlite', [
                'sqlite' => ['driver' => 'sqlite', 'database' => $database],
            ]), $directory);
            $path = $migrator->create('empty_change');
            $migrator->run();
            self::assertNotFalse(\file_put_contents($path, "\n// changed\n", FILE_APPEND));
            self::assertSame('modified', $migrator->status()[0]['status']);
        } finally {
            foreach (\glob($directory . '/*') ?: [] as $file) {
                \unlink($file);
            }
            \rmdir($directory);
            \unlink($database);
        }
    }

    public function testPretendDoesNotChangeSchemaOrHistory(): void
    {
        [$database, $directory, $migrator] = $this->fixture();
        try {
            $path = $migrator->create('create_audit_table');
            $source = (string) \file_get_contents($path);
            $source = \str_replace(
                '// Apply create_audit_table.',
                '$schema->create(\'audit\', static function (\\Puff\\Migration\\Blueprint $table): void { $table->id(); });',
                $source,
            );
            self::assertNotFalse(\file_put_contents($path, $source));

            $result = $migrator->run(true);
            self::assertCount(1, $result['migrations']);
            self::assertStringContainsString('CREATE TABLE', $result['statements'][0]);
            self::assertSame('pending', $migrator->status(false)[0]['status']);
        } finally {
            $this->removeFixture($database, $directory);
        }
    }

    public function testReportsMissingMigrationAndRefusesFurtherChanges(): void
    {
        [$database, $directory, $migrator] = $this->fixture();
        try {
            $path = $migrator->create('tracked_change');
            $migrator->run();
            self::assertTrue(\unlink($path));
            self::assertSame('missing', $migrator->status()[0]['status']);

            $this->expectException(MigrationException::class);
            $migrator->run();
        } finally {
            $this->removeFixture($database, $directory);
        }
    }

    /** @return array{string, string, Migrator} */
    private function fixture(): array
    {
        $database = \tempnam(\sys_get_temp_dir(), 'puff-db-');
        self::assertIsString($database);
        $directory = $database . '-migrations';
        self::assertTrue(\mkdir($directory));
        return [
            $database,
            $directory,
            new Migrator(new DatabaseManager('sqlite', [
                'sqlite' => ['driver' => 'sqlite', 'database' => $database],
            ]), $directory),
        ];
    }

    private function removeFixture(string $database, string $directory): void
    {
        foreach (\glob($directory . '/*') ?: [] as $file) {
            \unlink($file);
        }
        \rmdir($directory);
        \unlink($database);
    }
}
