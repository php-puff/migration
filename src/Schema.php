<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/migration
 * https://github.com/php-puff/migration/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Migration;

use Closure;
use Puff\Database\Contract\ConnectionInterface;
use Puff\Database\Driver;
use Puff\Migration\Schema\CompilerInterface;
use Puff\Migration\Schema\Identifier;
use Puff\Migration\Schema\MySqlCompiler;
use Puff\Migration\Schema\PostgresCompiler;
use Puff\Migration\Schema\SQLiteCompiler;

final class Schema
{
    /** @var list<string> */
    private array $statements = [];
    private readonly CompilerInterface $compiler;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly bool $pretend = false,
    ) {
        $this->compiler = match ($connection->driver()) {
            Driver::MySql => new MySqlCompiler(),
            Driver::PostgreSql => new PostgresCompiler(),
            Driver::SQLite => new SQLiteCompiler(),
        };
    }

    /** @param Closure(Blueprint): void $definition */
    public function create(string $table, Closure $definition): void
    {
        $blueprint = new Blueprint($table, true);
        $definition($blueprint);
        $this->run($this->compiler->create($blueprint));
    }

    /** @param Closure(Blueprint): void $definition */
    public function table(string $table, Closure $definition): void
    {
        $blueprint = new Blueprint($table, false);
        $definition($blueprint);
        $this->run($this->compiler->alter($blueprint));
    }

    public function rename(string $from, string $to): void
    {
        Identifier::validate($from);
        Identifier::validate($to);
        $this->run([$this->compiler->rename($from, $to)]);
    }

    public function drop(string $table): void
    {
        Identifier::validate($table);
        $this->run([$this->compiler->drop($table)]);
    }

    public function dropIfExists(string $table): void
    {
        Identifier::validate($table);
        $this->run([$this->compiler->drop($table, true)]);
    }

    public function hasTable(string $table): bool
    {
        [$sql, $parameters] = $this->compiler->hasTable(Identifier::validate($table));
        if ($this->pretend) {
            return false;
        }
        $row = $this->connection->fetchOne($sql, $parameters);
        return $row !== null && \array_filter($row, static fn (mixed $value): bool => $value !== null) !== [];
    }

    /** @param array<array-key, mixed> $parameters */
    public function execute(string $sql, array $parameters = []): int
    {
        if (\trim($sql) === '') {
            throw new \InvalidArgumentException('Schema SQL must not be empty.');
        }
        $this->statements[] = $sql;
        return $this->pretend ? 0 : $this->connection->execute($sql, $parameters);
    }

    /** @return list<string> */
    public function statements(): array
    {
        return $this->statements;
    }

    /** @param list<string> $statements */
    private function run(array $statements): void
    {
        foreach ($statements as $statement) {
            $this->execute($statement);
        }
    }
}
