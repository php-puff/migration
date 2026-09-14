<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/migration
 * https://github.com/php-puff/migration/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Migration\Tests;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Puff\Console\Input;
use Puff\Console\Output;
use Puff\Database\DatabaseManager;
use Puff\Di\Container;
use Puff\Migration\Command;
use Puff\Migration\Migrator;

#[RequiresPhpExtension('pdo_sqlite')]
final class CommandTest extends TestCase
{
    public function testProductionChangesRequireForce(): void
    {
        $previous = \getenv('PUFF_ENV');
        $database = \tempnam(\sys_get_temp_dir(), 'puff-command-');
        self::assertIsString($database);
        $directory = $database . '-migrations';
        self::assertTrue(\mkdir($directory));
        $stream = \fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        try {
            \putenv('PUFF_ENV=production');
            $container = new Container();
            $container->instance(Migrator::class, new Migrator(new DatabaseManager('sqlite', [
                'sqlite' => ['driver' => 'sqlite', 'database' => $database],
            ]), $directory));
            $command = new Command($container);

            try {
                $command->execute(Input::parse(['run'], $command->valueOptions(), $command->flagOptions()), new Output($stream));
                self::fail('Production migration should require --force.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('--force', $exception->getMessage());
            }

            self::assertSame(0, $command->execute(
                Input::parse(['run', '--force'], $command->valueOptions(), $command->flagOptions()),
                new Output($stream),
            ));
        } finally {
            $previous === false ? \putenv('PUFF_ENV') : \putenv('PUFF_ENV=' . $previous);
            \fclose($stream);
            foreach (\glob($directory . '/*') ?: [] as $file) {
                \unlink($file);
            }
            \rmdir($directory);
            \unlink($database);
        }
    }
}
