<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Console;

use App\IdentityAccess\Application\Command\IssueAdministratorRecovery\IssueAdministratorRecoveryCommand;
use App\IdentityAccess\Application\Command\IssueAdministratorRecovery\IssueAdministratorRecoveryHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'app:accounts:recovery-code', description: 'After offline identity verification, issue one recovery code to a private file; expires in 30 minutes.')]
final class RecoverMemberAccountCommand extends Command
{
    public function __construct(private readonly IssueAdministratorRecoveryHandler $issueAdministratorRecoveryHandler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('username', null, InputOption::VALUE_REQUIRED)->addOption('identity-reference', null, InputOption::VALUE_REQUIRED, 'Reference to the completed identity verification')->addOption('code-file', null, InputOption::VALUE_REQUIRED, 'New private file; never overwrite an existing file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = $input->getOption('username');
        $reference = $input->getOption('identity-reference');
        $file = $input->getOption('code-file');
        if (!is_string($username) || !is_string($reference) || !is_string($file) || '' === trim($file)) {
            $output->writeln('<error>username, identity-reference and code-file are required.</error>');

            return Command::INVALID;
        }
        try {
            ($this->issueAdministratorRecoveryHandler)(new IssueAdministratorRecoveryCommand($username, $reference, $file));
        } catch (Throwable $error) {
            $output->writeln('<error>Recovery code issuance failed. Check the account and destination file.</error>');

            return Command::FAILURE;
        }
        $output->writeln('Recovery code issued to the private file. Transfer securely after identity verification, then delete the file. It expires in 30 minutes. Existing sessions and previous recovery codes are revoked.');

        return Command::SUCCESS;
    }
}
