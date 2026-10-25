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
use Puff\Migration\Blueprint;
use Puff\Migration\Schema\MySqlCompiler;
use Puff\Migration\Schema\PostgresCompiler;
use Puff\Migration\Schema\SQLiteCompiler;

final class SchemaCompilerTest extends TestCase
{
    public function testCompilesPortableCreateStatements(): void
    {
        $blueprint = new Blueprint('users', true);
        $blueprint->id();
        $blueprint->string('email')->unique();
        $blueprint->boolean('active')->default(true);
        $blueprint->foreign('owner_id')->references('id')->on('owners')->onDelete('cascade');

        $mysql = (new MySqlCompiler())->create($blueprint);
        self::assertStringContainsString('AUTO_INCREMENT', $mysql[0]);
        self::assertStringContainsString('CREATE UNIQUE INDEX', $mysql[1]);

        $postgres = (new PostgresCompiler())->create($blueprint);
        self::assertStringContainsString('BIGSERIAL', $postgres[0]);

        $sqlite = (new SQLiteCompiler())->create($blueprint);
        self::assertStringContainsString('AUTOINCREMENT', $sqlite[0]);
    }

    public function testCompilesAlterAndDropStatements(): void
    {
        $blueprint = new Blueprint('users', false);
        $blueprint->string('nickname', 80)->nullable();
        $blueprint->renameColumn('name', 'full_name');
        $blueprint->dropColumn('legacy');
        $blueprint->dropIndex('users_email_unique');

        $sql = (new MySqlCompiler())->alter($blueprint);
        self::assertCount(4, $sql);
        self::assertStringContainsString('DROP INDEX', $sql[3]);
    }

    public function testPrefixesEveryTableReference(): void
    {
        $blueprint = new Blueprint('users', true);
        $blueprint->id();
        $blueprint->string('email')->unique();
        $blueprint->foreign('owner_id')->references('id')->on('owners');

        $mysql = new MySqlCompiler('123_');
        $sql = $mysql->create($blueprint);

        self::assertStringContainsString('CREATE TABLE `123_users`', $sql[0]);
        self::assertStringContainsString('REFERENCES `123_owners`', $sql[0]);
        self::assertStringContainsString('`123_users_email_unique` ON `123_users`', $sql[1]);
        self::assertSame(['123_users'], $mysql->hasTable('users')[1]);
        self::assertSame('DROP TABLE `123_users`', $mysql->drop('users'));
        self::assertSame('ALTER TABLE `123_users` RENAME TO `123_members`', $mysql->rename('users', 'members'));
    }

    public function testCompilesTableAndColumnComments(): void
    {
        $blueprint = new Blueprint('users', true);
        $blueprint->comment("Application's users");
        $blueprint->string('name')->comment("User's name");

        $mysql = (new MySqlCompiler('app_'))->create($blueprint);
        self::assertSame(
            "CREATE TABLE `app_users` (`name` VARCHAR(255) NOT NULL COMMENT 'User''s name') COMMENT='Application''s users'",
            $mysql[0],
        );

        $postgres = (new PostgresCompiler('app_'))->create($blueprint);
        self::assertSame("COMMENT ON TABLE \"app_users\" IS 'Application''s users'", $postgres[1]);
        self::assertSame("COMMENT ON COLUMN \"app_users\".\"name\" IS 'User''s name'", $postgres[2]);

        $sqlite = (new SQLiteCompiler('app_'))->create($blueprint);
        self::assertCount(1, $sqlite);
        self::assertStringNotContainsString('COMMENT', $sqlite[0]);
    }

    public function testCompilesAlterTableComments(): void
    {
        $blueprint = new Blueprint('users', false);
        $blueprint->comment('Registered users');
        $blueprint->string('nickname')->comment('Public name');

        $mysql = (new MySqlCompiler())->alter($blueprint);
        self::assertSame("ALTER TABLE `users` ADD COLUMN `nickname` VARCHAR(255) NOT NULL COMMENT 'Public name'", $mysql[0]);
        self::assertSame("ALTER TABLE `users` COMMENT = 'Registered users'", $mysql[1]);

        $postgres = (new PostgresCompiler())->alter($blueprint);
        self::assertSame("COMMENT ON TABLE \"users\" IS 'Registered users'", $postgres[1]);
        self::assertSame("COMMENT ON COLUMN \"users\".\"nickname\" IS 'Public name'", $postgres[2]);
    }
}
