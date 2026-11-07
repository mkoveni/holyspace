<?php

declare(strict_types=1);

namespace App\Communications\Application;

use App\Communications\Application\Contract\CommunicationAudit;
use App\Communications\Application\Contract\DeliveryReader;
use App\Communications\Application\Contract\EmailTransport;
use App\Communications\Application\Contract\TransportResult;
use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\EmailDelivery;
use App\Communications\Domain\Repository\DeliveryRepository;
use App\Shared\Application\IdGenerator;
use App\Shared\Application\UnitOfWork;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Sends due deliveries. The delivery is claimed in one transaction, sent outside any transaction (a slow mail
 * server never holds a database lock), and the outcome is recorded in another. Crashes between the two leave
 * SENDING, which recovery turns into UNKNOWN so nothing is ever sent twice without a person deciding.
 */
final readonly class DeliveryProcessor
{
    private const int STALE_AFTER_SECONDS = 300;
    private const int BATCH = 25;

    public function __construct(
        private DeliveryReader $reader,
        private DeliveryRepository $writer,
        private DispatchEligibility $policy,
        private EmailTransport $transport,
        private UnitOfWork $transactions,
        private CommunicationAudit $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
    ) {
    }

    /** @return int how many deliveries were attempted */
    public function runDue(): int
    {
        if (!$this->transport->configuration()->enabled) {
            return 0;
        }
        $this->transactions->execute(fn (): int => $this->writer->recoverStale($this->clock->now()->modify('-'.self::STALE_AFTER_SECONDS.' seconds'), $this->clock->now()));
        $due = $this->reader->due($this->clock->now(), self::BATCH);
        foreach ($due as $id) {
            $this->dispatch($id);
        }

        return \count($due);
    }

    public function dispatch(string $id): void
    {
        $sending = $this->transactions->execute(function () use ($id): ?EmailDelivery {
            $d = $this->writer->lock($id) ?? throw CommunicationException::missing('Delivery');
            $now = $this->clock->now();
            if (!$d->waiting() || $d->scheduledAt > $now) {
                return null;
            }
            try {
                $this->policy->require($d, $now);
            } catch (CommunicationException) {
                $this->writer->save($d->transition('BLOCKED', $d->scheduledAt, $now, 'ELIGIBILITY_CHANGED', false));
                $this->writer->event($id, 'BLOCKED', $now, $d->approvedBy, null, null, 'Sender, recipient or ministry eligibility changed. Review before retrying.');

                return null;
            }
            $active = $d->claim($now, $this->ids->uuid());
            $this->writer->save($active);
            $this->writer->event($id, 'SENDING', $now, $d->approvedBy, null, null, 'Dispatch started.');

            return $active;
        });
        if (null === $sending) {
            return;
        }

        try {
            $outcome = $this->transport->send(DeliveryMapping::snapshot($sending));
        } catch (Throwable) {
            $outcome = new TransportResult('UNKNOWN', 'TRANSPORT_UNCERTAIN', false);
        }

        $this->transactions->execute(function () use ($id, $sending, $outcome): void {
            $current = $this->writer->lock($id) ?? throw CommunicationException::missing('Delivery');
            if ('SENDING' !== $current->status || $current->attemptId !== $sending->attemptId) {
                return;
            }
            $now = $this->clock->now();
            $this->writer->save($current->transition($outcome->status, $current->scheduledAt, $now, $outcome->errorCode, $outcome->captured));
            $this->writer->event($id, $outcome->status, $now, $current->approvedBy, null, null, $outcome->captured ? 'Captured by the local test inbox.' : 'Transport outcome recorded.');
            $this->audit->record($current->approvedBy, 'communication.email.'.strtolower($outcome->status), $id, '', $now);
        });
    }
}
