<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

/** A current leader of the caller's community; the person id is used only to look up an available name. */
final readonly class LeaderEntry
{
    public function __construct(public string $personId, public string $role)
    {
    }
}
