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
use Puff\Migration\Index;

final class MySqlCompiler extends AbstractCompiler
{
    public function hasTable(string $table): array
    {
        $table = $this->table($table);
        return ['SELECT 1 AS exists_flag FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]];
    }

    protected function quoteCharacter(): string
    {
        return '`';
    }

    protected function supportsUnsigned(): bool
    {
        return true;
    }

    protected function autoIncrement(Column $column): string
    {
        return $column->autoIncrement ? ' AUTO_INCREMENT' : '';
    }

    protected function tableOptions(Blueprint $blueprint): string
    {
        return $blueprint->getComment() === null ? '' : ' COMMENT=' . $this->string($blueprint->getComment());
    }

    protected function columnComment(Column $column): string
    {
        return $column->comment === null ? '' : ' COMMENT ' . $this->string($column->comment);
    }

    protected function comments(Blueprint $blueprint): array
    {
        if ($blueprint->creating || $blueprint->getComment() === null) {
            return [];
        }
        return [\sprintf(
            'ALTER TABLE %s COMMENT = %s',
            $this->quote($this->table($blueprint->table)),
            $this->string($blueprint->getComment()),
        )];
    }

    protected function dropIndex(string $table, Index $index): string
    {
        $name = $index->name ?? throw new \LogicException('Dropped index must define its name.');
        return \sprintf('DROP INDEX %s ON %s', $this->quote($name), $this->quote($this->table($table)));
    }
}
