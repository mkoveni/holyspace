<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

final readonly class AppointmentView
{
    public function __construct(public LeadershipView $leadership, public string $accountName, public string $personName)
    {
    }
}
