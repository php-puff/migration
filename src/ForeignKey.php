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

final class ForeignKey
{
    public ?string $referencedColumn = null;
    public ?string $referencedTable = null;
    public ?string $deleteAction = null;
    public ?string $updateAction = null;

    public function __construct(public readonly string $column, public readonly ?string $name = null)
    {
        Identifier::validate($column);
        if ($name !== null) {
            Identifier::validate($name);
        }
    }

    public function references(string $column): self
    {
        $this->referencedColumn = Identifier::validate($column);
        return $this;
    }

    public function on(string $table): self
    {
        $this->referencedTable = Identifier::validate($table);
        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->deleteAction = $this->action($action);
        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->updateAction = $this->action($action);
        return $this;
    }

    private function action(string $action): string
    {
        $action = \strtoupper(\str_replace('_', ' ', $action));
        if (!\in_array($action, ['CASCADE', 'RESTRICT', 'NO ACTION', 'SET NULL'], true)) {
            throw new \InvalidArgumentException("Unsupported foreign key action [{$action}].");
        }
        return $action;
    }
}
