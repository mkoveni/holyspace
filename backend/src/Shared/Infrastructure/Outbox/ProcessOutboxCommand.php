<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Outbox;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:outbox:process', description: 'Deliver pending integration messages with idempotent consumers.')]
final class ProcessOutboxCommand extends Command
{
    public function __construct(private readonly OutboxProcessor $processor)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = 0;
        do {
            $batch = $this->processor->drain();
            $count += $batch;
        } while (100 === $batch);
        $output->writeln('Delivered '.$count.' integration messages.');

        return Command::SUCCESS;
    }
}
