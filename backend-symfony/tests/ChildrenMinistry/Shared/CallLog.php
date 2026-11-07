<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Shared;

/** An ordered record of the calls a test double received. */
final class CallLog
{
    /** @var list<string> */
    public array $entries = [];
}
