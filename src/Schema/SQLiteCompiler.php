<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/migration
 * https://github.com/php-puff/migration/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Migration\Schema;

use Puff\Migration\Blueprint;
use Puff\Migration\Column;
use Puff\Migration\Exception\UnsupportedSchemaOperation;

final class SQLiteCompiler extends AbstractCompiler
{
    public function hasTable(string $table): array
    {
        Identifier::validate($table);
        return ["SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]];
    }

    public function alter(Blueprint $blueprint): array
    {
        if ($blueprint->foreignKeys() !== []) {
            throw new UnsupportedSchemaOperation('SQLite cannot add foreign keys to an existing table.');
        }
        return parent::alter($blueprint);
    }

    protected function quoteCharacter(): string
    {
        return '"';
    }

    protected function column(Column $column): string
    {
        if ($column->autoIncrement) {
            return $this->quote($column->name) . ' INTEGER PRIMARY KEY AUTOINCREMENT';
        }
        return parent::column($column);
    }

    protected function type(Column $column): string
    {
        return match ($column->type) {
            'bigInteger', 'boolean' => 'INTEGER',
            'date', 'dateTime', 'timestamp' => 'TEXT',
            'json' => 'TEXT',
            default => parent::type($column),
        };
    }
}
