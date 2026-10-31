<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\Integration;

use App\Shared\Application\Outbox\IntegrationConsumer;
use App\Volunteering\Domain\Repository\VolunteeringRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('onechurch.integration_consumer')]
final readonly class OccurrenceConsumer implements IntegrationConsumer
{
    public function __construct(private VolunteeringRepository $records)
    {
    }

    public function name(): string
    {
        return 'volunteering-v1';
    }

    public function consume(string $type, array $payload): void
    {
        if (!in_array($type, ['EventOccurrenceCancelled', 'EventOccurrenceRescheduled'], true)) {
            return;
        }
        $status = 'EventOccurrenceCancelled' === $type ? 'cancelled' : 'needs_review';
        foreach ($this->records->all('shifts') as $shift) {
            if ($shift->occurrenceId !== $payload['occurrenceId'] || 'cancelled' === $shift->status) {
                continue;
            }
            $this->records->save($shift->changeStatus($status));
            foreach ($this->records->all('assignments') as $assignment) {
                if ($assignment->shiftId === $shift->id && in_array($assignment->status, ['invited', 'assigned', 'needs_review'], true)) {
                    $this->records->save($assignment->changeStatus($status));
                }
            }
        }
    }
}
