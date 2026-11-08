<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

/** Java's `Team` record as the team views return it. */
final readonly class TeamRecord
{
    public function __construct(public string $id, public string $name, public bool $active, public int $version)
    {
    }
}
