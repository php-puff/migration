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
use Puff\Database\Driver;
use Puff\Migration\Exception\MigrationException;

final class MigrationLock
{
    /** @var resource|null */
    private $file = null;
    private bool $locked = false;
    private readonly string $name;

    public function __construct(private readonly ConnectionInterface $connection)
    {
        $this->name = 'puff_migration_' . \substr($connection->fingerprint(), 0, 32);
    }

    public function acquire(): void
    {
        if ($this->locked) {
            return;
        }
        $acquired = match ($this->connection->driver()) {
            Driver::MySql => (int) ($this->connection->fetchOne('SELECT GET_LOCK(?, 10) AS acquired', [$this->name])['acquired'] ?? 0) === 1,
            Driver::PostgreSql => $this->boolean($this->connection->fetchOne(
                'SELECT pg_try_advisory_lock(?) AS acquired',
                [$this->key()],
            )['acquired'] ?? false),
            Driver::SQLite => $this->acquireFile(),
        };
        if (!$acquired) {
            throw new MigrationException('Unable to acquire the database migration lock.');
        }
        $this->locked = true;
    }

    public function release(): void
    {
        if (!$this->locked) {
            return;
        }
        match ($this->connection->driver()) {
            Driver::MySql => $this->connection->fetchOne('SELECT RELEASE_LOCK(?) AS released', [$this->name]),
            Driver::PostgreSql => $this->connection->fetchOne('SELECT pg_advisory_unlock(?) AS released', [$this->key()]),
            Driver::SQLite => $this->releaseFile(),
        };
        $this->locked = false;
    }

    /**
     * @template TResult
     * @param callable(): TResult $callback
     * @return TResult
     */
    public function synchronized(callable $callback): mixed
    {
        $this->acquire();
        try {
            return $callback();
        } finally {
            $this->release();
        }
    }

    private function key(): int
    {
        return (int) \hexdec(\substr(\hash('sha256', $this->name), 0, 7));
    }

    private function boolean(mixed $value): bool
    {
        return \filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }

    private function acquireFile(): bool
    {
        $file = \fopen(\sys_get_temp_dir() . '/' . $this->name . '.lock', 'c+');
        if ($file === false) {
            return false;
        }
        if (!\flock($file, LOCK_EX | LOCK_NB)) {
            \fclose($file);
            return false;
        }
        $this->file = $file;
        return true;
    }

    private function releaseFile(): null
    {
        if (\is_resource($this->file)) {
            \flock($this->file, LOCK_UN);
            \fclose($this->file);
        }
        $this->file = null;
        return null;
    }
}
