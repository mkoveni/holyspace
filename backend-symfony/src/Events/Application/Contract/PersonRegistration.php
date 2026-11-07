<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class PersonRegistration
{
    public function __construct(public RegistrationView $registration, public string $name)
    {
    }
}
