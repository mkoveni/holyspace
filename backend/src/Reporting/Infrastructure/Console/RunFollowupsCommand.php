<?php

declare(strict_types=1);

namespace App\Reporting\Infrastructure\Console;

use App\Reporting\Application\Command\SweepFollowupRules\SweepFollowupRulesCommand;
use App\Reporting\Application\Command\SweepFollowupRules\SweepFollowupRulesHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:followups:run', description: 'Evaluate enabled rules once per UTC day; create idempotent tasks only, never communications.')]
final class RunFollowupsCommand extends Command
{
    public function __construct(private readonly SweepFollowupRulesHandler $sweepFollowupRulesHandler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = ($this->sweepFollowupRulesHandler)(new SweepFollowupRulesCommand());
        $output->writeln(json_encode($result, JSON_THROW_ON_ERROR));

        return $result['paused'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
