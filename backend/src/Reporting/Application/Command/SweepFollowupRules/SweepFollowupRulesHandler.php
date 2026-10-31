<?php

declare(strict_types=1);

namespace App\Reporting\Application\Command\SweepFollowupRules;

use App\Reporting\Application\Command\RunFollowupRule\RunFollowupRuleCommand;
use App\Reporting\Application\Command\RunFollowupRule\RunFollowupRuleHandler;
use App\Reporting\Domain\Repository\ReportingRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class SweepFollowupRulesHandler
{
    public function __construct(private ReportingRepository $records, private RunFollowupRuleHandler $runFollowupRuleHandler)
    {
    }

    #[AsMessageHandler]
    public function __invoke(SweepFollowupRulesCommand $message): array
    {
        $created = 0;
        $paused = 0;
        foreach ($this->records->all('rules') as $rule) {
            if ($rule->active) {
                $result = ($this->runFollowupRuleHandler)(new RunFollowupRuleCommand($rule->id));
                $created += $result['created'];
                $paused += (int) $result['paused'];
            }
        }

        return ['created' => $created, 'paused' => $paused];
    }
}
