<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Application;

/** Records the order in which the handler takes its locks, which is what prevents deadlocks. */
final class LockLog
{
    /** @var list<string> */
    public array $entries = [];
}
