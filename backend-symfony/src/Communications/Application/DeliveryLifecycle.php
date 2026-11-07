<?php

declare(strict_types=1);

namespace App\Communications\Application;

use App\Communications\Application\Contract\CommunicationAudit;
use App\Communications\Application\Contract\DeliveryReader;
use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Application\Contract\DeliverySources;
use App\Communications\Application\Contract\EmailDeliveries;
use App\Communications\Application\Contract\EmailTransport;
use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\CommunicationRules;
use App\Communications\Domain\Repository\DeliveryRepository;
use App\Shared\Application\UnitOfWork;
use Psr\Clock\ClockInterface;

/** Queue, cancel, retry and provider receipts. Each runs in one transaction that joins its caller's. */
final readonly class DeliveryLifecycle implements EmailDeliveries
{
    private const int MAX_ATTEMPTS = 3;

    public function __construct(
        private DeliveryReader $reader,
        private DeliveryRepository $writer,
        private DeliverySources $sources,
        private DispatchEligibility $eligibility,
        private UnitOfWork $transactions,
        private CommunicationAudit $audit,
        private ClockInterface $clock,
        private EmailTransport $transport,
    ) {
    }

    public function queue(string $sourceContext, DeliverySnapshot $approved): void
    {
        $delivery = DeliveryMapping::aggregate($approved);
        if (!$delivery->waiting() || 0 !== $delivery->attempts || 0 !== $delivery->version) {
            throw CommunicationException::invalid('A new approved delivery is required.');
        }
        $this->transactions->execute(function () use ($sourceContext, $delivery): void {
            $this->writer->insert($delivery);
            $this->sources->associate($delivery->id, $sourceContext);
            $this->eligibility->authorize($delivery, $delivery->approvedBy, 'queue');
            $this->eligibility->require($delivery, $this->clock->now());
            $this->audit->record($delivery->approvedBy, 'communication.email.queued', $delivery->id, '', $this->clock->now());
            $this->writer->event($delivery->id, $delivery->status, $this->clock->now(), $delivery->approvedBy, null, null, 'Reviewed recipient and message approved.');
        });
    }

    public function cancel(string $actor, string $id, int $expected, string $reason): void
    {
        $note = CommunicationRules::text($reason, 500);
        $this->transactions->execute(function () use ($actor, $id, $expected, $note): void {
            $d = $this->writer->lock($id) ?? throw CommunicationException::missing('Delivery');
            $this->eligibility->authorize($d, $actor, 'cancel');
            CommunicationRules::version($d->version, $expected);
            if (!\in_array($d->status, ['QUEUED', 'SCHEDULED', 'FAILED', 'BLOCKED'], true)) {
                throw CommunicationException::conflict('An in-progress, accepted or uncertain send cannot be cancelled.');
            }
            $now = $this->clock->now();
            $this->writer->save($d->transition('CANCELLED', $d->scheduledAt, $now, '', $d->captured));
            $this->writer->event($id, 'CANCELLED', $now, $actor, null, null, $note);
            $this->audit->record($actor, 'communication.email.cancelled', $id, '', $now);
        });
    }

    public function retry(string $actor, string $id, int $expected, string $reason): void
    {
        $note = CommunicationRules::text($reason, 500);
        $this->transactions->execute(function () use ($actor, $id, $expected, $note): void {
            $d = $this->writer->lock($id) ?? throw CommunicationException::missing('Delivery');
            $this->eligibility->authorize($d, $actor, 'retry');
            CommunicationRules::version($d->version, $expected);
            if (!\in_array($d->status, ['FAILED', 'BLOCKED'], true)) {
                throw CommunicationException::conflict('Only definite failures or blocked deliveries may be retried. Reconcile uncertain outcomes with the provider.');
            }
            if ($d->attempts >= self::MAX_ATTEMPTS) {
                throw CommunicationException::conflict('The delivery attempt limit is reached.');
            }
            $now = $this->clock->now();
            $approved = $d->approveRetry($actor, $now);
            $this->eligibility->require($approved, $now);
            $this->writer->save($approved);
            $this->writer->event($id, 'RETRY_APPROVED', $now, $actor, null, null, $note);
            $this->audit->record($actor, 'communication.email.retry', $id, '', $now);
        });
    }

    public function receipt(string $actor, string $id, string $messageId, string $key, string $status, string $reference): void
    {
        $eventKey = CommunicationRules::text($key, 200);
        $ref = CommunicationRules::text($reference, 500);
        if (!\in_array($status, ['DELIVERED', 'FAILED'], true)) {
            throw CommunicationException::invalid('Choose a delivered or failed provider outcome.');
        }
        $hash = DeliveryFingerprint::of($id, $messageId, $status, $ref);
        $this->transactions->execute(function () use ($actor, $id, $messageId, $eventKey, $status, $ref, $hash): void {
            $d = $this->writer->lock($id) ?? throw CommunicationException::missing('Delivery');
            $this->eligibility->authorize($d, $actor, 'receipt');
            $prior = $this->reader->receipt($eventKey);
            if (null !== $prior) {
                if ($prior->hash !== $hash || $prior->deliveryId !== $id) {
                    throw CommunicationException::conflict('Provider event identifier already used.');
                }

                return;
            }
            if ($this->transport->configuration()->capture || $d->captured || $d->messageId !== $messageId || !\in_array($d->status, ['SENT', 'UNKNOWN'], true)) {
                throw CommunicationException::conflict('Receipt does not match a live SMTP-accepted delivery.');
            }
            $now = $this->clock->now();
            $this->writer->save($d->transition($status, $d->scheduledAt, $now, 'FAILED' === $status ? 'PROVIDER_FAILURE' : '', false));
            $this->writer->event($id, $status, $now, $actor, $eventKey, $hash, $ref);
            $this->audit->record($actor, 'communication.email.receipt', $id, '', $now);
        });
    }
}
