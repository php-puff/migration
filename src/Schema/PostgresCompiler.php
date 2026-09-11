<?php

/*
 * PHP Fiber Framework
 * https://github.com/php-puff/migration
 * https://github.com/php-puff/migration/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Migration\Schema;

use Puff\Migration\Column;

final class PostgresCompiler extends AbstractCompiler
{
    public function hasTable(string $table): array
    {
        Identifier::validate($table);
        return ['SELECT to_regclass(?) AS name', [$table]];
    }

    protected function quoteCharacter(): string
    {
        return '"';
    }

    protected function type(Column $column): string
    {
        if ($column->autoIncrement) {
            return $column->type === 'bigInteger' ? 'BIGSERIAL' : 'SERIAL';
        }
        return match ($column->type) {
            'dateTime' => 'TIMESTAMP',
            'binary' => 'BYTEA',
            default => parent::type($column),
        };
    }

    protected function autoIncrement(Column $column): string
    {
        return '';
    }
}
