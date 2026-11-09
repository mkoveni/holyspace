<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Console;

use App\Media\Application\OrphanSweep;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Removes picture files no database row records and stale temporary files. Never touches anything under a day old. */
#[AsCommand(name: 'app:media:gc', description: 'Remove orphaned picture files and stale temporary files')]
final class GarbageCollectConsoleCommand extends Command
{
    public function __construct(private readonly OrphanSweep $sweep)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->sweep->run();
        $output->writeln(sprintf('Removed %d orphaned files and %d temporary files.', $result['orphans'], $result['temporary']));

        return Command::SUCCESS;
    }
}
