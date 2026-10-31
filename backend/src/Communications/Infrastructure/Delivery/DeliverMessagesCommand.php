<?php

declare(strict_types=1);

namespace App\Communications\Infrastructure\Delivery;

use App\Communications\Application\Command\DeliverNextMessage\DeliverNextMessageCommand;
use App\Communications\Application\Command\DeliverNextMessage\DeliverNextMessageHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:communications:deliver', description: 'Deliver previously queued, consented campaign messages.')]
final class DeliverMessagesCommand extends Command
{
    public function __construct(private readonly DeliverNextMessageHandler $deliverNextMessageHandler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = 0;
        while ($count < 100 && ($this->deliverNextMessageHandler)(new DeliverNextMessageCommand())) {
            ++$count;
        }
        $output->writeln('Processed '.$count.' delivery attempts.');

        return Command::SUCCESS;
    }
}
