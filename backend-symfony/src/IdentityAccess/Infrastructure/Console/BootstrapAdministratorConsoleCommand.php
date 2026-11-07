<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Console;

use App\IdentityAccess\Application\Command\BootstrapAdministrator\BootstrapAdministratorCommand;
use App\Shared\Application\CommandBus;
use App\Shared\Domain\ContextException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Operator-only: creates the first administrator, reading the password from a file rather than argv. */
#[AsCommand(name: 'app:identity:bootstrap-admin', description: 'Create the first administrator account')]
final class BootstrapAdministratorConsoleCommand extends Command
{
    public function __construct(private readonly CommandBus $commands)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('username', null, InputOption::VALUE_REQUIRED)
            ->addOption('email', null, InputOption::VALUE_REQUIRED)
            ->addOption('display-name', null, InputOption::VALUE_REQUIRED)
            ->addOption('password-file', null, InputOption::VALUE_REQUIRED, 'File containing the password (max 4096 bytes)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) $input->getOption('password-file');

        if ('' === $file || !is_file($file) || filesize($file) > 4096) {
            $io->error('A readable bootstrap password file of at most 4096 bytes is required.');

            return Command::FAILURE;
        }
        $password = preg_replace('/\r?\n\z/', '', (string) file_get_contents($file)) ?? '';

        try {
            $this->commands->dispatch(new BootstrapAdministratorCommand(
                (string) $input->getOption('username'),
                (string) $input->getOption('email'),
                (string) $input->getOption('display-name'),
                $password,
            ));
        } catch (ContextException $error) {
            $io->error($error->getMessage());

            return Command::FAILURE;
        }

        $io->success('First administrator created.');

        return Command::SUCCESS;
    }
}
