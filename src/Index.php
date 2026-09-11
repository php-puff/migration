<?php

/*
 * PHP Fiber Framework
 * https://github.com/php-puff/migration
 * https://github.com/php-puff/migration/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Migration;

use Puff\Migration\Schema\Identifier;

final readonly class Index
{
    /** @param list<string> $columns */
    public function __construct(
        public array $columns,
        public bool $unique = false,
        public ?string $name = null,
        public bool $drop = false,
    ) {
        if ($columns === []) {
            throw new \InvalidArgumentException('Index columns must not be empty.');
        }
        foreach ($columns as $column) {
            Identifier::validate($column);
        }
        if ($name !== null) {
            Identifier::validate($name);
        }
    }
}
