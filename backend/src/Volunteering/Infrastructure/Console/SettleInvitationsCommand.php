<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\Console;

use App\Volunteering\Application\Command\SettleVolunteerInvitations\SettleVolunteerInvitationsCommand;
use App\Volunteering\Application\Command\SettleVolunteerInvitations\SettleVolunteerInvitationsHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:volunteering:settle', description: 'Apply occurrence changes and expire pending volunteer invitations.')]
final class SettleInvitationsCommand extends Command
{
    public function __construct(private readonly SettleVolunteerInvitationsHandler $handler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ($this->handler)(new SettleVolunteerInvitationsCommand());

        return Command::SUCCESS;
    }
}
