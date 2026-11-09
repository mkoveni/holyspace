<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Console;

use App\Media\Application\DeletionDrain;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Deletes the files of replaced or removed pictures. The scheduler runs it every minute; an operator can run it by hand. */
#[AsCommand(name: 'app:media:delete-pending', description: 'Delete the files of replaced or removed pictures')]
final class DeletePendingConsoleCommand extends Command
{
    public function __construct(private readonly DeletionDrain $drain)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('Removed %d queued pictures.', $this->drain->run()));

        return Command::SUCCESS;
    }
}
