<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class RegistrationPage
{
    /** @param list<PersonRegistration> $registrations */
    public function __construct(public array $registrations, public int $total, public RegistrationCounts $counts)
    {
    }
}
