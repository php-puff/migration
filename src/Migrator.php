<?php

/*
 * PHP Fiber Framework
 * https://github.com/php-puff/migration
 * https://github.com/php-puff/migration/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Migration;

use Puff\Database\Contract\ConnectionInterface;
use Puff\Database\Contract\DatabaseManagerInterface;
use Puff\Database\Driver;
use Puff\Migration\Exception\MigrationException;

final class Migrator
{
    private readonly ConnectionInterface $connection;
    private readonly MigrationRepository $repository;
    private readonly MigrationLoader $loader;
    private readonly MigrationLock $lock;

    public function __construct(
        private readonly DatabaseManagerInterface $databases,
        private readonly string $directory,
        string|null $connection = null,
    ) {
        $this->connection = $databases->connection($connection);
        $this->repository = new MigrationRepository($this->connection);
        $this->loader = new MigrationLoader($directory);
        $this->lock = new MigrationLock($this->connection);
    }

    /** @return list<array{name: string, status: string, batch: int|null, applied_at: string|null}> */
    public function status(bool $createRepository = true): array
    {
        $files = $this->loader->files();
        $records = $this->repository->all($createRepository);
        $status = [];
        foreach ($files as $name => $file) {
            $record = $records[$name] ?? null;
            $state = match (true) {
                $record === null => 'pending',
                !\hash_equals($record->checksum, $file->checksum) => 'modified',
                default => 'applied',
            };
            $status[] = [
                'name' => $name,
                'status' => $state,
                'batch' => $record?->batch,
                'applied_at' => $record?->appliedAt,
            ];
            unset($records[$name]);
        }
        foreach ($records as $record) {
            $status[] = [
                'name' => $record->migration,
                'status' => 'missing',
                'batch' => $record->batch,
                'applied_at' => $record->appliedAt,
            ];
        }
        \usort($status, static fn (array $left, array $right): int => $left['name'] <=> $right['name']);
        return $status;
    }

    /** @return array{migrations: list<string>, statements: list<string>} */
    public function run(bool $pretend = false): array
    {
        return $this->lock->synchronized(function () use ($pretend): array {
            $this->assertHistory($pretend);
            $files = $this->loader->files();
            $records = $this->repository->all(!$pretend);
            $pending = \array_diff_key($files, $records);
            if ($pending === []) {
                return ['migrations' => [], 'statements' => []];
            }
            $batch = $this->repository->nextBatch();
            $migrations = [];
            $statements = [];
            foreach ($pending as $file) {
                $result = $this->apply($file, 'up', $batch, $pretend);
                $migrations[] = $file->name;
                $statements = [...$statements, ...$result];
            }
            return ['migrations' => $migrations, 'statements' => $statements];
        });
    }

    /** @return array{migrations: list<string>, statements: list<string>} */
    public function rollback(?int $steps = null, bool $pretend = false): array
    {
        if ($steps !== null && $steps < 1) {
            throw new \InvalidArgumentException('Rollback steps must be positive.');
        }
        return $this->lock->synchronized(function () use ($steps, $pretend): array {
            $this->assertHistory($pretend);
            $files = $this->loader->files();
            $records = \array_values($this->repository->all(!$pretend));
            \usort($records, static fn (MigrationRecord $left, MigrationRecord $right): int => $right->migration <=> $left->migration);
            if ($records === []) {
                return ['migrations' => [], 'statements' => []];
            }
            if ($steps === null) {
                $batch = \max(\array_map(static fn (MigrationRecord $record): int => $record->batch, $records));
                $records = \array_values(\array_filter(
                    $records,
                    static fn (MigrationRecord $record): bool => $record->batch === $batch,
                ));
            } else {
                $records = \array_slice($records, 0, $steps);
            }
            $migrations = [];
            $statements = [];
            foreach ($records as $record) {
                $file = $files[$record->migration] ?? throw new MigrationException(
                    "Applied migration file [{$record->migration}] is missing.",
                );
                $result = $this->apply($file, 'down', $record->batch, $pretend);
                $migrations[] = $file->name;
                $statements = [...$statements, ...$result];
            }
            return ['migrations' => $migrations, 'statements' => $statements];
        });
    }

    public function create(string $name, ?string $table = null): string
    {
        return $this->loader->create($name, $table);
    }

    public function using(?string $connection = null, ?string $directory = null): self
    {
        if ($connection === null && $directory === null) {
            return $this;
        }
        return new self($this->databases, $directory ?? $this->directory, $connection);
    }

    /** @return list<string> */
    private function apply(MigrationFile $file, string $direction, int $batch, bool $pretend): array
    {
        $migration = $file->load();
        $schema = new Schema($this->connection, $pretend);
        $started = \hrtime(true);
        $operation = function () use ($migration, $schema, $direction, $file, $batch, $started, $pretend): void {
            $direction === 'up' ? $migration->up($schema) : $migration->down($schema);
            if ($pretend) {
                return;
            }
            if ($direction === 'up') {
                $this->repository->record($file, $batch, (\hrtime(true) - $started) / 1_000_000);
            } else {
                $this->repository->delete($file->name);
            }
        };
        try {
            if (!$pretend && $this->connection->driver()->supportsTransactionalDdl()) {
                $this->connection->transaction(static function (ConnectionInterface $_connection) use ($operation): void {
                    $operation();
                });
            } else {
                $operation();
            }
        } catch (\Throwable $exception) {
            if (!$pretend && $this->connection->driver() === Driver::MySql) {
                throw new MigrationException(
                    "MySQL migration [{$file->name}] failed. DDL may have been partially applied; inspect the schema before retrying.",
                    0,
                    $exception,
                );
            }
            throw $exception;
        }
        return $schema->statements();
    }

    private function assertHistory(bool $pretend): void
    {
        foreach ($this->status(!$pretend) as $migration) {
            if (\in_array($migration['status'], ['modified', 'missing'], true)) {
                throw new MigrationException(
                    "Migration [{$migration['name']}] is {$migration['status']}; repair migration history before continuing.",
                );
            }
        }
    }
}
