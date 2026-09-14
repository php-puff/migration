<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/migration
 * https://github.com/php-puff/migration/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Migration;

use Puff\Migration\Exception\MigrationException;
use Puff\Migration\Schema\Identifier;

final readonly class MigrationLoader
{
    public function __construct(private string $directory)
    {
    }

    /** @return array<string, MigrationFile> */
    public function files(): array
    {
        $paths = \glob($this->directory . '/*.php') ?: [];
        \sort($paths);
        $files = [];
        foreach ($paths as $path) {
            $name = \pathinfo($path, PATHINFO_FILENAME);
            if (!\preg_match('/^\d{8}_\d{6}_[a-z][a-z0-9_]*$/', $name)) {
                throw new MigrationException("Invalid migration filename [{$name}].");
            }
            if (isset($files[$name])) {
                throw new MigrationException("Duplicate migration [{$name}].");
            }
            $files[$name] = new MigrationFile($name, $path);
        }
        return $files;
    }

    public function create(string $name, ?string $table = null): string
    {
        if (!\preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException('Migration name must use lowercase letters, numbers and underscores.');
        }
        if (!\is_dir($this->directory) && !@\mkdir($this->directory, 0777, true) && !\is_dir($this->directory)) {
            throw new MigrationException("Unable to create migration directory [{$this->directory}].");
        }
        $prefix = (new \DateTimeImmutable())->format('Ymd_His');
        $path = $this->directory . '/' . $prefix . '_' . $name . '.php';
        if (\is_file($path)) {
            throw new MigrationException("Migration [{$path}] already exists.");
        }
        if ($table !== null) {
            $table = Identifier::validate($table);
        }
        $stub = \file_get_contents(\dirname(__DIR__) . '/stub/' . ($table === null ? 'create' : 'table') . '.stub');
        if ($stub === false) {
            throw new MigrationException('Unable to read migration stub.');
        }
        $source = \str_replace(['%NAME%', '%TABLE%'], [$name, $table ?? ''], $stub);
        if (\file_put_contents($path, $source, LOCK_EX) === false) {
            throw new MigrationException("Unable to write migration [{$path}].");
        }
        return $path;
    }
}
