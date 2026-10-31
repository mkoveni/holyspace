<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\SettleVolunteerResponses;

use App\Shared\Application\CQRS\Command;

final readonly class SettleVolunteerResponsesCommand implements Command
{
    public function __construct()
    {
    }
}
