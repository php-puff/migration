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
    private ?string $comment = null;

    public function __construct(public readonly string $table, public readonly bool $creating)
    {
        Identifier::validate($table);
    }

    public function id(string $name = 'id'): Column
    {
        return $this->bigIncrements($name);
    }

    public function increments(string $name): Column
    {
        return $this->unsignedInteger($name, true)->primary();
    }

    public function integerIncrements(string $name): Column
    {
        return $this->increments($name);
    }

    public function tinyIncrements(string $name): Column
    {
        return $this->unsignedTinyInteger($name, true)->primary();
    }

    public function smallIncrements(string $name): Column
    {
        return $this->unsignedSmallInteger($name, true)->primary();
    }

    public function mediumIncrements(string $name): Column
    {
        return $this->unsignedMediumInteger($name, true)->primary();
    }

    public function bigIncrements(string $name): Column
    {
        return $this->unsignedBigInteger($name, true)->primary();
    }

    public function integer(string $name, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        return $this->integerColumn($name, 'integer', $autoIncrement, $unsigned);
    }

    public function tinyInteger(string $name, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        return $this->integerColumn($name, 'tinyInteger', $autoIncrement, $unsigned);
    }

    public function smallInteger(string $name, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        return $this->integerColumn($name, 'smallInteger', $autoIncrement, $unsigned);
    }

    public function mediumInteger(string $name, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        return $this->integerColumn($name, 'mediumInteger', $autoIncrement, $unsigned);
    }

    public function bigInteger(string $name, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        return $this->integerColumn($name, 'bigInteger', $autoIncrement, $unsigned);
    }

    public function unsignedInteger(string $name, bool $autoIncrement = false): Column
    {
        return $this->integer($name, $autoIncrement, true);
    }

    public function unsignedTinyInteger(string $name, bool $autoIncrement = false): Column
    {
        return $this->tinyInteger($name, $autoIncrement, true);
    }

    public function unsignedSmallInteger(string $name, bool $autoIncrement = false): Column
    {
        return $this->smallInteger($name, $autoIncrement, true);
    }

    public function unsignedMediumInteger(string $name, bool $autoIncrement = false): Column
    {
        return $this->mediumInteger($name, $autoIncrement, true);
    }

    public function unsignedBigInteger(string $name, bool $autoIncrement = false): Column
    {
        return $this->bigInteger($name, $autoIncrement, true);
    }

    public function foreignId(string $name): Column
    {
        return $this->unsignedBigInteger($name);
    }

    public function char(string $name, int $length = 255): Column
    {
        return $this->lengthColumn($name, 'char', $length);
    }

    public function string(string $name, int $length = 255): Column
    {
        return $this->lengthColumn($name, 'string', $length);
    }

    public function tinyText(string $name): Column
    {
        return $this->column($name, 'tinyText');
    }

    public function text(string $name): Column
    {
        return $this->column($name, 'text');
    }

    public function mediumText(string $name): Column
    {
        return $this->column($name, 'mediumText');
    }

    public function longText(string $name): Column
    {
        return $this->column($name, 'longText');
    }

    public function float(string $name, int $precision = 53): Column
    {
        return $this->lengthColumn($name, 'float', $precision);
    }

    public function double(string $name): Column
    {
        return $this->column($name, 'double');
    }

    public function boolean(string $name): Column
    {
        return $this->column($name, 'boolean');
    }

    /** @param list<string> $allowed */
    public function enum(string $name, array $allowed): Column
    {
        if ($allowed === []) {
            throw new \InvalidArgumentException('Enum values must not be empty.');
        }
        foreach ($allowed as $value) {
            if ($value === '' || \str_contains($value, "\0")) {
                throw new \InvalidArgumentException('Enum values must be non-empty strings without null bytes.');
            }
        }
        return $this->column($name, 'enum', allowed: $allowed);
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

    public function dateTime(string $name, ?int $precision = null): Column
    {
        return $this->temporalColumn($name, 'dateTime', $precision);
    }

    public function dateTimeTz(string $name, ?int $precision = null): Column
    {
        return $this->temporalColumn($name, 'dateTimeTz', $precision);
    }

    public function time(string $name, ?int $precision = null): Column
    {
        return $this->temporalColumn($name, 'time', $precision);
    }

    public function timeTz(string $name, ?int $precision = null): Column
    {
        return $this->temporalColumn($name, 'timeTz', $precision);
    }

    public function timestamp(string $name, ?int $precision = null): Column
    {
        return $this->temporalColumn($name, 'timestamp', $precision);
    }

    public function timestampTz(string $name, ?int $precision = null): Column
    {
        return $this->temporalColumn($name, 'timestampTz', $precision);
    }

    public function json(string $name): Column
    {
        return $this->column($name, 'json');
    }

    public function jsonb(string $name): Column
    {
        return $this->column($name, 'jsonb');
    }

    public function binary(string $name): Column
    {
        return $this->column($name, 'binary');
    }

    public function year(string $name): Column
    {
        return $this->column($name, 'year');
    }

    public function uuid(string $name = 'uuid'): Column
    {
        return $this->column($name, 'uuid');
    }

    public function foreignUuid(string $name): Column
    {
        return $this->uuid($name);
    }

    public function ulid(string $name = 'ulid'): Column
    {
        return $this->column($name, 'ulid');
    }

    public function foreignUlid(string $name): Column
    {
        return $this->ulid($name);
    }

    public function ipAddress(string $name = 'ip_address'): Column
    {
        return $this->column($name, 'ipAddress');
    }

    public function macAddress(string $name = 'mac_address'): Column
    {
        return $this->column($name, 'macAddress');
    }

    public function timestamps(?int $precision = null): void
    {
        $this->timestamp('created_at', $precision)->nullable();
        $this->timestamp('updated_at', $precision)->nullable();
    }

    public function nullableTimestamps(?int $precision = null): void
    {
        $this->timestamps($precision);
    }

    public function timestampsTz(?int $precision = null): void
    {
        $this->timestampTz('created_at', $precision)->nullable();
        $this->timestampTz('updated_at', $precision)->nullable();
    }

    public function nullableTimestampsTz(?int $precision = null): void
    {
        $this->timestampsTz($precision);
    }

    public function datetimes(?int $precision = null): void
    {
        $this->dateTime('created_at', $precision)->nullable();
        $this->dateTime('updated_at', $precision)->nullable();
    }

    public function softDeletes(string $name = 'deleted_at', ?int $precision = null): Column
    {
        return $this->timestamp($name, $precision)->nullable();
    }

    public function softDeletesTz(string $name = 'deleted_at', ?int $precision = null): Column
    {
        return $this->timestampTz($name, $precision)->nullable();
    }

    public function softDeletesDatetime(string $name = 'deleted_at', ?int $precision = null): Column
    {
        return $this->dateTime($name, $precision)->nullable();
    }

    public function rememberToken(): Column
    {
        return $this->string('remember_token', 100)->nullable();
    }

    public function morphs(string $name, ?string $indexName = null): void
    {
        $this->numericMorphs($name, $indexName);
    }

    public function nullableMorphs(string $name, ?string $indexName = null): void
    {
        $this->nullableNumericMorphs($name, $indexName);
    }

    public function numericMorphs(string $name, ?string $indexName = null): void
    {
        $this->morphColumns($name, 'bigInteger', false, $indexName);
    }

    public function nullableNumericMorphs(string $name, ?string $indexName = null): void
    {
        $this->morphColumns($name, 'bigInteger', true, $indexName);
    }

    public function uuidMorphs(string $name, ?string $indexName = null): void
    {
        $this->morphColumns($name, 'uuid', false, $indexName);
    }

    public function nullableUuidMorphs(string $name, ?string $indexName = null): void
    {
        $this->morphColumns($name, 'uuid', true, $indexName);
    }

    public function ulidMorphs(string $name, ?string $indexName = null): void
    {
        $this->morphColumns($name, 'ulid', false, $indexName);
    }

    public function nullableUlidMorphs(string $name, ?string $indexName = null): void
    {
        $this->morphColumns($name, 'ulid', true, $indexName);
    }

    public function comment(string $comment): self
    {
        if (\str_contains($comment, "\0")) {
            throw new \InvalidArgumentException('Table comment must not contain null bytes.');
        }
        $this->comment = $comment;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
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

    public function dropTimestamps(): void
    {
        $this->dropColumn('created_at', 'updated_at');
    }

    public function dropTimestampsTz(): void
    {
        $this->dropTimestamps();
    }

    public function dropSoftDeletes(string $name = 'deleted_at'): void
    {
        $this->dropColumn($name);
    }

    public function dropSoftDeletesTz(string $name = 'deleted_at'): void
    {
        $this->dropSoftDeletes($name);
    }

    public function dropRememberToken(): void
    {
        $this->dropColumn('remember_token');
    }

    public function dropMorphs(string $name, ?string $indexName = null): void
    {
        $columns = [Identifier::validate($name . '_type'), Identifier::validate($name . '_id')];
        $this->indexes[] = new Index($columns, false, $indexName, true);
        $this->dropColumn(...$columns);
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

    /** @param list<string>|null $allowed */
    private function column(
        string $name,
        string $type,
        ?int $length = null,
        ?int $scale = null,
        ?array $allowed = null,
    ): Column {
        $column = new Column($name, $type, $length, $scale, $allowed);
        $this->columns[] = $column;
        return $column;
    }

    private function integerColumn(string $name, string $type, bool $autoIncrement, bool $unsigned): Column
    {
        return $this->column($name, $type)->autoIncrement($autoIncrement)->unsigned($unsigned);
    }

    private function lengthColumn(string $name, string $type, int $length): Column
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('Column length must be positive.');
        }
        return $this->column($name, $type, $length);
    }

    private function temporalColumn(string $name, string $type, ?int $precision): Column
    {
        if ($precision !== null && ($precision < 0 || $precision > 6)) {
            throw new \InvalidArgumentException('Temporal precision must be between 0 and 6.');
        }
        return $this->column($name, $type, $precision);
    }

    private function morphColumns(string $name, string $type, bool $nullable, ?string $indexName): void
    {
        Identifier::validate($name);
        $this->string($name . '_type')->nullable($nullable);
        $id = $type === 'bigInteger'
            ? $this->unsignedBigInteger($name . '_id')
            : $this->column($name . '_id', $type);
        $id->nullable($nullable);
        $this->index([$name . '_type', $name . '_id'], $indexName);
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
