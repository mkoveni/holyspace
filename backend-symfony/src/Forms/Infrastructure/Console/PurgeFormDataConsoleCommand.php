<?php

declare(strict_types=1);

namespace App\Forms\Infrastructure\Console;

use App\Forms\Application\Command\PurgeExpiredFormData\PurgeExpiredFormDataCommand;
use App\Shared\Application\CommandBus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Daily retention job: hard-deletes form responses past their retention date. */
#[AsCommand(name: 'app:forms:purge', description: 'Delete form responses whose retention period has ended')]
final class PurgeFormDataConsoleCommand extends Command
{
    public function __construct(private readonly CommandBus $commands)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('Deleted %d expired form responses.', $this->commands->dispatch(new PurgeExpiredFormDataCommand())));

        return Command::SUCCESS;
    }
}
