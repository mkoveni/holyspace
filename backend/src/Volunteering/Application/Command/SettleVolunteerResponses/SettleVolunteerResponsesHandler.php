<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\SettleVolunteerResponses;

use App\Volunteering\Application\Command\SettleVolunteerInvitations\SettleVolunteerInvitationsCommand;
use App\Volunteering\Application\Command\SettleVolunteerInvitations\SettleVolunteerInvitationsHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class SettleVolunteerResponsesHandler
{
    public function __construct(private SettleVolunteerInvitationsHandler $settleVolunteerInvitationsHandler)
    {
    }

    #[AsMessageHandler]
    public function __invoke(SettleVolunteerResponsesCommand $message): void
    {
        ($this->settleVolunteerInvitationsHandler)(new SettleVolunteerInvitationsCommand());
    }
}
