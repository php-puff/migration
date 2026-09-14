<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/migration
 * https://github.com/php-puff/migration/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Migration;

use Puff\Migration\Schema\Identifier;

final class Column
{
    public bool $nullable = false;
    public bool $unsigned = false;
    public bool $primary = false;
    public bool $unique = false;
    public bool $indexed = false;
    public bool $autoIncrement = false;
    public bool $hasDefault = false;
    public mixed $default = null;

    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?int $length = null,
        public readonly ?int $scale = null,
    ) {
        Identifier::validate($name);
    }

    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;
        return $this;
    }

    public function unsigned(bool $value = true): self
    {
        $this->unsigned = $value;
        return $this;
    }

    public function primary(bool $value = true): self
    {
        $this->primary = $value;
        return $this;
    }

    public function unique(bool $value = true): self
    {
        $this->unique = $value;
        return $this;
    }

    public function index(bool $value = true): self
    {
        $this->indexed = $value;
        return $this;
    }

    public function autoIncrement(bool $value = true): self
    {
        $this->autoIncrement = $value;
        return $this;
    }

    public function default(mixed $value): self
    {
        if (!\is_scalar($value) && $value !== null) {
            throw new \InvalidArgumentException('Column default must be scalar or null.');
        }
        $this->hasDefault = true;
        $this->default = $value;
        return $this;
    }
}
