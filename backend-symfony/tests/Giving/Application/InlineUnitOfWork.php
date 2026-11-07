<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Shared\Application\UnitOfWork;

/** Runs the operation directly and counts calls, so a test can see a contract call ran inside a unit of work. */
final class InlineUnitOfWork implements UnitOfWork
{
    public int $calls = 0;

    public function execute(callable $operation): mixed
    {
        ++$this->calls;

        return $operation();
    }
}
