<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\PurgeExpiredFormData;

use App\Forms\Domain\Repository\FormRepository;
use App\Forms\Domain\Repository\ResponseRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Hard-deletes responses whose retention has ended. Leaderboards and summaries are queries over the rows, so they go with
 * them. The audit row records the form and a count only, never any answer.
 */
final readonly class PurgeExpiredFormDataHandler implements CommandHandler
{
    public function __construct(private ResponseRepository $responses, private FormRepository $forms, private ClockInterface $clock)
    {
    }

    public function handle(PurgeExpiredFormDataCommand $command): int
    {
        $now = $this->clock->now();
        $deleted = $this->responses->deleteExpired($now);
        foreach ($deleted as $formId => $count) {
            $this->forms->audit($formId, 'PURGED', $count.' responses', null, $now);
        }

        return array_sum($deleted);
    }
}
