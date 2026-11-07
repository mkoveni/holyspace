<?php

declare(strict_types=1);

namespace App\Communications\Infrastructure\Console;

use App\Communications\Application\PushDeliveryProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Sends due device pushes and checks their receipts. The scheduler runs it every 15 seconds; it does nothing while push is off. */
#[AsCommand(name: 'app:communications:dispatch-push', description: 'Send due device push notifications and check receipts')]
final class DispatchDuePushConsoleCommand extends Command
{
    public function __construct(private readonly PushDeliveryProcessor $processor)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('Attempted %d due push jobs.', $this->processor->runDue()));

        return Command::SUCCESS;
    }
}
