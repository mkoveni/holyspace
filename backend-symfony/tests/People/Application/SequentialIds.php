<?php

declare(strict_types=1);

namespace App\Tests\People\Application;

use App\Shared\Application\IdGenerator;

/** In-memory test double: no database, no identity context. */
final class SequentialIds implements IdGenerator
{
    private int $next = 0;

    public function uuid(): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', ++$this->next);
    }
}
