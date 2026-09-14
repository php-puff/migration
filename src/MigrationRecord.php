<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/migration
 * https://github.com/php-puff/migration/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Migration;

final readonly class MigrationRecord
{
    public function __construct(
        public string $migration,
        public int $batch,
        public string $checksum,
        public string $appliedAt,
        public float $durationMs,
    ) {
    }
}
