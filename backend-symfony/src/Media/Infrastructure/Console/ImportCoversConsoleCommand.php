<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Console;

use App\Media\Application\Command\ImportCover\ImportCoverCommand;
use App\Media\Application\Port\LegacyCoverSource;
use App\Media\Domain\MediaException;
use App\Shared\Application\CommandBus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Copies the event and community covers stored in the database into Media. Safe to run any number of times: a slot that
 * already has a picture is skipped. One transaction per cover, so a bad image never blocks the others.
 */
#[AsCommand(name: 'app:media:import-covers', description: 'Move database-stored event and community covers into Media')]
final class ImportCoversConsoleCommand extends Command
{
    public function __construct(private readonly LegacyCoverSource $legacy, private readonly CommandBus $commands)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $counts = ['imported' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($this->legacy->covers() as $cover) {
            try {
                $result = $this->commands->dispatch(new ImportCoverCommand($cover->kind, $cover->ownerId, $cover->imageId));
                ++$counts['skipped' === $result ? 'skipped' : 'imported'];
            } catch (MediaException $e) {
                ++$counts['failed'];
                $output->writeln(sprintf('<error>%s %s (image %s): %s</error>', $cover->kind->value, $cover->ownerId, $cover->imageId, $e->getMessage()));
            }
        }
        $output->writeln(sprintf('Imported %d, skipped %d, failed %d.', $counts['imported'], $counts['skipped'], $counts['failed']));

        return 0 === $counts['failed'] ? Command::SUCCESS : Command::FAILURE;
    }
}
