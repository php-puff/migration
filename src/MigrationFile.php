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

final readonly class MigrationFile
{
    public string $checksum;

    public function __construct(public string $name, public string $path)
    {
        $checksum = \hash_file('sha256', $path);
        if ($checksum === false) {
            throw new MigrationException("Unable to hash migration [{$path}].");
        }
        $this->checksum = $checksum;
    }

    public function load(): Migration
    {
        $migration = require $this->path;
        if (!$migration instanceof Migration) {
            throw new MigrationException("Migration file [{$this->path}] must return a Migration instance.");
        }
        return $migration;
    }
}
