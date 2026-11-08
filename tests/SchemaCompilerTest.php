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
        self::assertStringContainsString('"active" BOOLEAN NOT NULL DEFAULT TRUE', $postgres[0]);

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
        self::assertStringContainsString('DROP INDEX', $sql[1]);
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

    public function testBuildsNumericUuidAndUlidMorphs(): void
    {
        $blueprint = new Blueprint('images', true);
        $blueprint->morphs('imageable', typeComment: 'Image type', idComment: 'Image ID');
        $blueprint->numericMorphs('numeric');
        $blueprint->nullableUuidMorphs('owner');
        $blueprint->ulidMorphs('tenant', 'images_tenant_index');

        self::assertSame(
            ['imageable_type', 'imageable_id', 'numeric_type', 'numeric_id', 'owner_type', 'owner_id', 'tenant_type', 'tenant_id'],
            \array_map(static fn ($column): string => $column->name, $blueprint->columns()),
        );
        self::assertSame('Image type', $blueprint->columns()[0]->comment);
        self::assertSame('Image ID', $blueprint->columns()[1]->comment);
        self::assertTrue($blueprint->columns()[3]->unsigned);
        self::assertTrue($blueprint->columns()[4]->nullable);
        self::assertTrue($blueprint->columns()[5]->nullable);

        $mysql = (new MySqlCompiler())->create($blueprint);
        self::assertStringContainsString('`imageable_id` CHAR(26) NOT NULL', $mysql[0]);
        self::assertStringContainsString('`numeric_id` BIGINT UNSIGNED NOT NULL', $mysql[0]);
        self::assertStringContainsString('`owner_id` CHAR(36)', $mysql[0]);
        self::assertStringContainsString('`tenant_id` CHAR(26) NOT NULL', $mysql[0]);
        self::assertCount(5, $mysql);

        $postgres = (new PostgresCompiler())->create($blueprint);
        self::assertStringContainsString('"owner_id" UUID', $postgres[0]);
    }

    public function testDropsMorphColumnsAndGeneratedIndex(): void
    {
        $blueprint = new Blueprint('images', false);
        $blueprint->dropMorphs('imageable');

        $sql = (new MySqlCompiler('app_'))->alter($blueprint);
        self::assertSame('DROP INDEX `app_images_imageable_type_imageable_id_index` ON `app_images`', $sql[0]);
        self::assertSame('ALTER TABLE `app_images` DROP COLUMN `imageable_type`', $sql[1]);
        self::assertSame('ALTER TABLE `app_images` DROP COLUMN `imageable_id`', $sql[2]);
    }

    public function testCompilesCommonBlueprintColumnHelpers(): void
    {
        $blueprint = new Blueprint('profiles', true);
        $blueprint->increments('sequence');
        $blueprint->tinyInteger('rank');
        $blueprint->char('code', 8);
        $blueprint->enum('state', ['active', 'disabled']);
        $blueprint->jsonb('metadata');
        $blueprint->dateTimeTz('verified_at', 3)->nullable();
        $blueprint->year('birth_year');
        $blueprint->ipAddress();
        $blueprint->macAddress();
        $blueprint->softDeletes();
        $blueprint->rememberToken();

        $mysql = (new MySqlCompiler())->create($blueprint)[0];
        self::assertStringContainsString('`sequence` INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT', $mysql);
        self::assertStringContainsString("`state` ENUM('active', 'disabled')", $mysql);
        self::assertStringContainsString('`verified_at` DATETIME(3)', $mysql);

        $postgres = (new PostgresCompiler())->create($blueprint)[0];
        self::assertStringContainsString('"state" VARCHAR(255) CHECK', $postgres);
        self::assertStringContainsString('"metadata" JSONB', $postgres);
        self::assertStringContainsString('"verified_at" TIMESTAMP(3) WITH TIME ZONE', $postgres);

        $sqlite = (new SQLiteCompiler())->create($blueprint)[0];
        self::assertStringContainsString('"metadata" TEXT', $sqlite);
        self::assertStringContainsString('"birth_year" INTEGER', $sqlite);
    }
}
