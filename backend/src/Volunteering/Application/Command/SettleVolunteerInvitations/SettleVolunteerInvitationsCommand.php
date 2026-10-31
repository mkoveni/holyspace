<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\SettleVolunteerInvitations;

use App\Shared\Application\CQRS\Command;

final readonly class SettleVolunteerInvitationsCommand implements Command
{
    public function __construct()
    {
    }
}
