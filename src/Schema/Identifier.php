<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/migration
 * https://github.com/php-puff/migration/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Migration\Schema;

final class Identifier
{
    public static function validate(string $identifier): string
    {
        if (!\preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("Invalid schema identifier [{$identifier}].");
        }
        return $identifier;
    }
}
