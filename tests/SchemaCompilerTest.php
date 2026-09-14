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
}
