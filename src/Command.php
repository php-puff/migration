<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/migration
 * https://github.com/php-puff/migration/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Migration;

use Psr\Container\ContainerInterface;
use Puff\Console\Contract;
use Puff\Console\Input;
use Puff\Console\Output;

final readonly class Command implements Contract
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function name(): string
    {
        return 'migration';
    }

    public function description(): string
    {
        return 'Create, run, inspect or roll back database migrations';
    }

    public function usage(): string
    {
        return 'migration <create|run|status|rollback> [name] [options]';
    }

    public function valueOptions(): array
    {
        return ['connection' => 'c', 'path' => 'p', 'step' => 's', 'table' => 't'];
    }

    public function flagOptions(): array
    {
        return ['pretend' => '', 'force' => 'f'];
    }

    public function execute(Input $input, Output $output): int
    {
        $action = $input->argument(0);
        if ($action === null || !\in_array($action, ['create', 'run', 'status', 'rollback'], true)) {
            throw new \InvalidArgumentException('Migration action must be create, run, status or rollback.');
        }
        $migrator = $this->migrator($input);
        if (\in_array($action, ['run', 'rollback'], true)) {
            $this->guardProduction($input);
        }
        return match ($action) {
            'create' => $this->create($migrator, $input, $output),
            'run' => $this->result($migrator->run($input->hasOption('pretend')), 'Applied', $output),
            'status' => $this->status($migrator, $output),
            'rollback' => $this->result(
                $migrator->rollback($this->steps($input), $input->hasOption('pretend')),
                'Rolled back',
                $output,
            ),
        };
    }

    private function migrator(Input $input): Migrator
    {
        $migrator = $this->container->get(Migrator::class);
        if (!$migrator instanceof Migrator) {
            throw new \RuntimeException('Database migration service is not configured.');
        }
        $connection = $input->option('connection');
        $path = $input->option('path');
        return $migrator->using(
            \is_string($connection) ? $connection : null,
            \is_string($path) ? $path : null,
        );
    }

    private function create(Migrator $migrator, Input $input, Output $output): int
    {
        $name = \trim((string) $input->argument(1));
        if ($name === '') {
            throw new \InvalidArgumentException('Migration name is required.');
        }
        $table = $input->option('table');
        $output->write('Generated migration [' . $migrator->create($name, \is_string($table) ? $table : null) . '].');
        return 0;
    }

    /** @param array{migrations: list<string>, statements: list<string>} $result */
    private function result(array $result, string $verb, Output $output): int
    {
        foreach ($result['statements'] as $statement) {
            $output->write('[SQL] ' . $statement);
        }
        if ($result['migrations'] === []) {
            $output->write('Nothing to do.');
            return 0;
        }
        foreach ($result['migrations'] as $migration) {
            $output->write("{$verb} [{$migration}].");
        }
        return 0;
    }

    private function status(Migrator $migrator, Output $output): int
    {
        $status = $migrator->status();
        if ($status === []) {
            $output->write('No migrations found.');
            return 0;
        }
        foreach ($status as $migration) {
            $output->write('[' . $migration['status'] . '] ' . $migration['name']);
        }
        return 0;
    }

    private function steps(Input $input): ?int
    {
        $steps = $input->option('step');
        if ($steps === null) {
            return null;
        }
        if (!\is_string($steps) || !\ctype_digit($steps) || (int) $steps < 1) {
            throw new \InvalidArgumentException('Rollback step must be a positive integer.');
        }
        return (int) $steps;
    }

    private function guardProduction(Input $input): void
    {
        $environment = \strtolower((string) (\getenv('PUFF_ENV') ?: ''));
        if (\in_array($environment, ['prod', 'production'], true) && !$input->hasOption('force')) {
            throw new \RuntimeException('Use --force to run migrations when PUFF_ENV is production.');
        }
    }
}
