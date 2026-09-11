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

final class Blueprint
{
    /** @var list<Column> */
    private array $columns = [];
    /** @var list<Index> */
    private array $indexes = [];
    /** @var list<ForeignKey> */
    private array $foreignKeys = [];
    /** @var list<string> */
    private array $dropColumns = [];
    /** @var list<array{from: string, to: string}> */
    private array $renameColumns = [];

    public function __construct(public readonly string $table, public readonly bool $creating)
    {
        Identifier::validate($table);
    }

    public function id(string $name = 'id'): Column
    {
        return $this->bigInteger($name)->unsigned()->autoIncrement()->primary();
    }

    public function integer(string $name): Column
    {
        return $this->column($name, 'integer');
    }

    public function bigInteger(string $name): Column
    {
        return $this->column($name, 'bigInteger');
    }

    public function string(string $name, int $length = 255): Column
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('String length must be positive.');
        }
        return $this->column($name, 'string', $length);
    }

    public function text(string $name): Column
    {
        return $this->column($name, 'text');
    }

    public function boolean(string $name): Column
    {
        return $this->column($name, 'boolean');
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2): Column
    {
        if ($precision < 1 || $scale < 0 || $scale > $precision) {
            throw new \InvalidArgumentException('Decimal precision and scale are invalid.');
        }
        return $this->column($name, 'decimal', $precision, $scale);
    }

    public function date(string $name): Column
    {
        return $this->column($name, 'date');
    }

    public function dateTime(string $name): Column
    {
        return $this->column($name, 'dateTime');
    }

    public function timestamp(string $name): Column
    {
        return $this->column($name, 'timestamp');
    }

    public function json(string $name): Column
    {
        return $this->column($name, 'json');
    }

    public function binary(string $name): Column
    {
        return $this->column($name, 'binary');
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    /** @param string|list<string> $columns */
    public function index(string|array $columns, ?string $name = null): void
    {
        $this->indexes[] = new Index($this->columnsList($columns), false, $name);
    }

    /** @param string|list<string> $columns */
    public function unique(string|array $columns, ?string $name = null): void
    {
        $this->indexes[] = new Index($this->columnsList($columns), true, $name);
    }

    public function dropIndex(string $name): void
    {
        $this->indexes[] = new Index([$name], false, Identifier::validate($name), true);
    }

    public function foreign(string $column, ?string $name = null): ForeignKey
    {
        $foreign = new ForeignKey($column, $name);
        $this->foreignKeys[] = $foreign;
        return $foreign;
    }

    public function dropColumn(string ...$columns): void
    {
        foreach ($columns as $column) {
            $this->dropColumns[] = Identifier::validate($column);
        }
    }

    public function renameColumn(string $from, string $to): void
    {
        $this->renameColumns[] = [
            'from' => Identifier::validate($from),
            'to' => Identifier::validate($to),
        ];
    }

    /** @return list<Column> */
    public function columns(): array
    {
        return $this->columns;
    }

    /** @return list<Index> */
    public function indexes(): array
    {
        return $this->indexes;
    }

    /** @return list<ForeignKey> */
    public function foreignKeys(): array
    {
        return $this->foreignKeys;
    }

    /** @return list<string> */
    public function droppedColumns(): array
    {
        return $this->dropColumns;
    }

    /** @return list<array{from: string, to: string}> */
    public function renamedColumns(): array
    {
        return $this->renameColumns;
    }

    private function column(string $name, string $type, ?int $length = null, ?int $scale = null): Column
    {
        $column = new Column($name, $type, $length, $scale);
        $this->columns[] = $column;
        return $column;
    }

    /**
     * @param string|list<string> $columns
     * @return list<string>
     */
    private function columnsList(string|array $columns): array
    {
        return \is_string($columns) ? [$columns] : $columns;
    }
}
