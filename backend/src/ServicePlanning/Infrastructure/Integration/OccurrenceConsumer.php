<?php

declare(strict_types=1);

namespace App\ServicePlanning\Infrastructure\Integration;

use App\ServicePlanning\Domain\Repository\ServicePlanningRepository;
use App\Shared\Application\Outbox\IntegrationConsumer;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('onechurch.integration_consumer')]
final readonly class OccurrenceConsumer implements IntegrationConsumer
{
    public function __construct(private ServicePlanningRepository $records)
    {
    }

    public function name(): string
    {
        return 'service-planning-v1';
    }

    public function consume(string $type, array $payload): void
    {
        if (!in_array($type, ['EventOccurrenceCancelled', 'EventOccurrenceRescheduled'], true)) {
            return;
        }
        foreach ($this->records->all('plans') as $plan) {
            if ($plan->occurrenceId === $payload['occurrenceId'] && 'cancelled' !== $plan->status) {
                $this->records->save($plan->changeStatus('EventOccurrenceCancelled' === $type ? 'cancelled' : 'needs_review'));
            }
        }
    }
}
