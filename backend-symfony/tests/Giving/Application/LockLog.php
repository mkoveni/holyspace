<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

/** Shared by the in-memory fakes of one test so the order of row locks across them can be asserted. */
final class LockLog
{
    /** @var list<string> */
    public array $locks = [];
}
