# Puff Migration

`puff/migration` manages database schemas through `puff/database` contracts. It is independent of Eloquent, Cycle, and every other ORM: it never scans models or entities and it does not generate schema from ORM metadata.

## Installation and configuration

```bash
composer require --dev puff/migration
```

The package publishes a minimal `config/migration.php` file. Only the default directory is configurable:

```php
return [
    'path' => dirname(__DIR__) . '/database/migrations',
];
```

The database connection defaults to `database.default`. A command may temporarily select another configured connection or migration directory.

## CLI

```bash
./puff migration create create_users_table
./puff migration create add_nickname --table=users
./puff migration run
./puff migration status
./puff migration rollback
./puff migration rollback --step=2
```

| Option | Meaning |
|---|---|
| `--connection=<name>`, `-c <name>` | Use a named database connection |
| `--path=<directory>`, `-p <directory>` | Override the migration directory for this command |
| `--step=<count>`, `-s <count>` | Roll back the newest number of migrations instead of the latest batch |
| `--table=<name>`, `-t <name>` | Generate a table-alteration migration template |
| `--pretend` | Compile and print SQL without changing the schema or history |
| `--force`, `-f` | Permit `run` or `rollback` when `PUFF_ENV=production` |

Without arguments, rollback reverts the newest batch. `--force` never bypasses checksum validation or migration locks.

## Migration files

`migration create` writes a timestamped file such as `20260829_120000_create_users_table.php`. The file returns an object implementing `Migration`; class names and a shared abstract base are not used:

```php
use Puff\Migration\Blueprint;
use Puff\Migration\Migration;
use Puff\Migration\Schema;

return new class implements Migration {
    public function up(Schema $schema): void
    {
        $schema->create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('name', 100)->nullable()->comment('Display name');
            $table->boolean('active')->default(true);
            $table->decimal('credit', 12, 2)->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->comment('Application users');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('users');
    }
};
```

## Schema DSL

Table operations:

```php
$schema->create('users', $definition);
$schema->table('users', $definition);
$schema->rename('users', 'members');
$schema->drop('members');
$schema->dropIfExists('members');
$schema->hasTable('members');
$schema->execute('CREATE VIEW active_users AS SELECT * FROM users WHERE active = 1');
```

Columns include `id`, `integer`, `bigInteger`, `string`, `text`, `boolean`, `decimal`, `date`, `dateTime`, `timestamp`, `json`, `binary`, and `timestamps`. Modifiers include `nullable`, `default`, `unsigned`, `primary`, `unique`, `index`, `autoIncrement`, and `comment`. Table comments use `$table->comment('...')`. MySQL and PostgreSQL persist comments; SQLite ignores them because it has no native table or column comment support.

Alter tables and indexes explicitly:

```php
$schema->table('users', static function (Blueprint $table): void {
    $table->string('nickname', 80)->nullable()->index();
    $table->renameColumn('name', 'display_name');
    $table->dropColumn('legacy');
    $table->unique(['tenant_id', 'email'], 'users_tenant_email_unique');
    $table->dropIndex('users_email_unique');
});
```

Foreign keys validate identifiers and support `CASCADE`, `RESTRICT`, `NO ACTION`, and `SET NULL` actions:

```php
$table->foreign('team_id')
    ->references('id')
    ->on('teams')
    ->onDelete('cascade')
    ->onUpdate('restrict');
```

MySQL, PostgreSQL, and SQLite have separate DDL compilers. Operations that a driver cannot safely express throw `UnsupportedSchemaOperation`; use `Schema::execute()` as the explicit native-SQL escape hatch.

## History integrity

The `puff_migrations` table records:

| Column | Purpose |
|---|---|
| `migration` | Timestamped migration filename without `.php` |
| `batch` | Group used by default rollback |
| `checksum` | SHA-256 of the applied file |
| `applied_at` | Application timestamp |
| `duration_ms` | Migration execution time |

Status values are `pending`, `applied`, `modified`, and `missing`. An applied file whose checksum changed is `modified`; a recorded file no longer present on disk is `missing`. Both states block run and rollback until history is repaired. `--force` does not weaken this check.

An older `puff_migrations` table is upgraded in place with missing metadata columns. Existing records without a checksum intentionally appear modified and require review rather than being trusted silently.

## Transactions and locks

- PostgreSQL and SQLite migrations run in DDL transactions.
- MySQL DDL is not treated as transactional. A failure does not write history and reports that the schema may require manual repair.
- MySQL uses `GET_LOCK`; PostgreSQL uses advisory locks; SQLite uses an exclusive file lock keyed by the connection fingerprint.
- Identifier validation applies to migration names, tables, columns, indexes, and foreign keys.

Locks prevent two processes from applying the same migration plan concurrently. Pretend mode still takes the migration lock, ensuring its plan is generated against a stable history.

## ORM boundary

Migrations depend on `ConnectionInterface`, `DatabaseManagerInterface`, and `Driver`, not on an ORM. Installing, removing, or switching Eloquent and Cycle does not change the migration API, database configuration, or `puff_migrations` records.

## Quality checks

```bash
composer test
composer analyse
composer validate --strict
```

The integration suite requires `pdo_sqlite`. Release validation must execute it with the extension enabled; skipped database tests are not accepted as a pass.
