<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application\Teams;

/** The order in which the fakes were locked, as "kind:id" entries. */
final class LockJournal
{
    /** @var list<string> */
    public array $entries = [];
}
