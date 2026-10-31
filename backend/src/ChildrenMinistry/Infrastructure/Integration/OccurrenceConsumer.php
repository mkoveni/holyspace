<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Infrastructure\Integration;

use App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository;
use App\Shared\Application\Outbox\IntegrationConsumer;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('onechurch.integration_consumer')]
final readonly class OccurrenceConsumer implements IntegrationConsumer
{
    public function __construct(private ChildrenMinistryRepository $records)
    {
    }

    public function name(): string
    {
        return 'children-ministry-v1';
    }

    public function consume(string $type, array $payload): void
    {
        if (!in_array($type, ['EventOccurrenceCancelled', 'EventOccurrenceRescheduled'], true)) {
            return;
        }
        foreach ($this->records->all('sessions') as $session) {
            if ($session->occurrenceId === $payload['occurrenceId'] && 'cancelled' !== $session->status) {
                $this->records->save($session->changeStatus('EventOccurrenceCancelled' === $type ? 'cancelled' : 'needs_review'));
            }
        }
        // Open check-ins are intentionally preserved until supervised checkout.
    }
}
