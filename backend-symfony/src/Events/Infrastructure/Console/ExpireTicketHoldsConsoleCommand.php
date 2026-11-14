<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Console;

use App\Events\Application\TicketHoldSweeper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Frees the seats of public ticket holds that lapsed without payment. */
#[AsCommand(name: 'app:events:expire-ticket-holds', description: 'Expire public ticket orders whose hold lapsed')]
final class ExpireTicketHoldsConsoleCommand extends Command
{
    public function __construct(private readonly TicketHoldSweeper $sweeper)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(\sprintf('Expired %d ticket holds.', $this->sweeper->sweep()));

        return Command::SUCCESS;
    }
}
