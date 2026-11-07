<?php

declare(strict_types=1);

namespace App\Communications\Infrastructure\Console;

use App\Communications\Application\DeliveryProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Sends deliveries whose time has come. The scheduler runs it every 15 seconds; an operator can run it by hand. */
#[AsCommand(name: 'app:communications:dispatch-due', description: 'Send approved emails that are due')]
final class DispatchDueDeliveriesConsoleCommand extends Command
{
    public function __construct(private readonly DeliveryProcessor $processor)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('Attempted %d due deliveries.', $this->processor->runDue()));

        return Command::SUCCESS;
    }
}
