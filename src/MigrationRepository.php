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

final class MigrationRepository
{
    private const TABLE = 'puff_migrations';

    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function exists(): bool
    {
        return (new Schema($this->connection))->hasTable(self::TABLE);
    }

    public function ensure(): void
    {
        if ($this->exists()) {
            $this->ensureColumns();
            return;
        }
        (new Schema($this->connection))->create(self::TABLE, static function (Blueprint $table): void {
            $table->string('migration')->primary();
            $table->integer('batch');
            $table->string('checksum', 64);
            $table->string('applied_at', 32);
            $table->decimal('duration_ms', 12, 3);
        });
    }

    /** @return array<string, MigrationRecord> */
    public function all(bool $create = true): array
    {
        if ($create) {
            $this->ensure();
        } elseif (!$this->exists()) {
            return [];
        }
        $records = [];
        foreach ($this->connection->fetchAll(
            'SELECT migration, batch, checksum, applied_at, duration_ms FROM ' . self::TABLE . ' ORDER BY migration',
        ) as $row) {
            $migration = $row['migration'] ?? null;
            if (!\is_string($migration)) {
                continue;
            }
            $records[$migration] = new MigrationRecord(
                $migration,
                (int) ($row['batch'] ?? 0),
                (string) ($row['checksum'] ?? ''),
                (string) ($row['applied_at'] ?? ''),
                (float) ($row['duration_ms'] ?? 0),
            );
        }
        return $records;
    }

    public function nextBatch(): int
    {
        if (!$this->exists()) {
            return 1;
        }
        $row = $this->connection->fetchOne('SELECT MAX(batch) AS batch FROM ' . self::TABLE);
        return (int) ($row['batch'] ?? 0) + 1;
    }

    public function record(MigrationFile $file, int $batch, float $durationMs): void
    {
        $this->connection->execute(
            'INSERT INTO ' . self::TABLE . ' (migration, batch, checksum, applied_at, duration_ms) VALUES (?, ?, ?, ?, ?)',
            [
                $file->name,
                $batch,
                $file->checksum,
                (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                \round($durationMs, 3),
            ],
        );
    }

    public function delete(string $migration): void
    {
        $this->connection->execute(
            'DELETE FROM ' . self::TABLE . ' WHERE migration = ?',
            [$migration],
        );
    }

    private function ensureColumns(): void
    {
        foreach (['batch', 'checksum', 'applied_at', 'duration_ms'] as $column) {
            try {
                $this->connection->fetchOne('SELECT ' . $column . ' FROM ' . self::TABLE . ' LIMIT 1');
                continue;
            } catch (\PDOException $exception) {
                if (!$this->isMissingColumn($exception)) {
                    throw $exception;
                }
                (new Schema($this->connection))->table(
                    self::TABLE,
                    static function (Blueprint $table) use ($column): void {
                        match ($column) {
                            'batch' => $table->integer('batch')->default(0),
                            'checksum' => $table->string('checksum', 64)->default(''),
                            'applied_at' => $table->string('applied_at', 32)->default(''),
                            'duration_ms' => $table->decimal('duration_ms', 12, 3)->default(0),
                        };
                    },
                );
            }
        }
    }

    private function isMissingColumn(\PDOException $exception): bool
    {
        $code = (string) $exception->getCode();
        $message = \strtolower($exception->getMessage());
        return \in_array($code, ['42S22', '42703'], true)
            || \str_contains($message, 'no such column')
            || \str_contains($message, 'unknown column')
            || \str_contains($message, 'undefined column');
    }
}
