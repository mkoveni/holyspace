<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

/** Records the order rows are locked in, so a test can assert the documented lock order. */
final class LockLog
{
    /** @var list<string> */
    public array $locks = [];
}
