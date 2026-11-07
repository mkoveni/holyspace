<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

/** The member's own booking on an occurrence; never anyone else's. */
final readonly class MemberBooking
{
    public function __construct(public string $id, public string $status, public int $version, public ?string $attendance)
    {
    }
}
