<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Migration;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Operator-only entry point: migrates the schema without starting HTTP or workers. */
#[AsCommand(name: 'app:migrate', description: 'Apply pending SQL schema migrations')]
final class MigrateCommand extends Command
{
    public function __construct(private readonly SqlMigrationRunner $runner)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $applied = $this->runner->migrate();
        } catch (MigrationException $error) {
            $io->error($error->getMessage());

            return Command::FAILURE;
        }

        $io->success([] === $applied ? 'Schema is up to date.' : 'Applied migrations: V'.implode(', V', $applied));

        return Command::SUCCESS;
    }
}
