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
use Puff\Console\CommandProvider;
use Puff\Console\Contract;

/** Registers database-migration commands. */
final class ConsoleProvider implements CommandProvider
{
    /** @return iterable<Contract> */
    public function commands(string $root, ContainerInterface $container): iterable
    {
        yield new Command($container);
    }
}
