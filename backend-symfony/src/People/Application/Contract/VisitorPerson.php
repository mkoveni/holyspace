<?php

declare(strict_types=1);

namespace App\People\Application\Contract;

/** The person a visitor check-in belongs to, and whether the registrar had to create them. */
final readonly class VisitorPerson
{
    public function __construct(public string $personId, public bool $firstTime)
    {
    }
}
