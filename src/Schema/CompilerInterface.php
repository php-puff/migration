<?php

/*
 * PHP Fiber Framework
 * https://github.com/php-puff/migration
 * https://github.com/php-puff/migration/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Migration\Schema;

use Puff\Migration\Blueprint;

interface CompilerInterface
{
    /** @return list<string> */
    public function create(Blueprint $blueprint): array;

    /** @return list<string> */
    public function alter(Blueprint $blueprint): array;

    public function rename(string $from, string $to): string;

    public function drop(string $table, bool $ifExists = false): string;

    /** @return array{string, list<mixed>} */
    public function hasTable(string $table): array;
}
