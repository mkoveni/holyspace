<?php

declare(strict_types=1);

namespace App\Communications\Application;

use App\Communications\Application\Contract\PushSettings;
use App\Communications\Application\Contract\PushTokens;
use App\Communications\Application\Contract\PushTransport;
use App\Communications\Application\Contract\PushTransportResult;
use App\Communications\Domain\PushDeliveryPolicy;
use App\Communications\Domain\PushDevice;
use App\Communications\Domain\PushJob;
use App\Communications\Domain\Repository\PushQueue;
use App\IdentityAccess\Application\Contract\AccountDirectory;
use App\IdentityAccess\Application\Contract\SessionReader;
use App\Shared\Application\UnitOfWork;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Trusted outbox worker for device push. A job is claimed in one transaction, sent or checked outside any transaction
 * (a slow provider never holds a lock), and recorded in another. Its only authority is what is persisted now: the
 * member's opt-in at the queued version, the device at the queued revision, an active account and a live session.
 * Anything else cancels the job, and a send that may have reached the provider is never repeated.
 */
final readonly class PushDeliveryProcessor
{
    public function __construct(
        private PushQueue $queue,
        private PushTransport $transport,
        private PushTokens $tokens,
        private PushSettings $settings,
        private AccountDirectory $accounts,
        private SessionReader $sessions,
        private UnitOfWork $transactions,
        private ClockInterface $clock,
    ) {
    }

    /** @return int how many due jobs were attempted */
    public function runDue(): int
    {
        if (!$this->configured()) {
            return 0;
        }
        $this->transactions->execute(function (): void {
            $this->queue->recover($this->clock->now());
        });
        $due = $this->queue->due($this->clock->now());
        foreach ($due as $id) {
            $this->dispatch($id);
        }

        return \count($due);
    }

    public function dispatch(string $id): void
    {
        if (!$this->configured()) {
            return;
        }
        $job = $this->transactions->execute(fn (): ?PushJob => $this->queue->claim($id, $this->clock->now()));
        if (null === $job) {
            return;
        }
        $at = $this->clock->now();
        $device = $this->queue->eligible($job, $at);
        if (null === $device || !$this->stillAllowed($device, $at) || PushDeliveryPolicy::expired($job->createdAt, $at)) {
            $this->finish($job, PushJob::CANCELLED, $at, $job->ticketId, 'ELIGIBILITY_CHANGED');

            return;
        }

        $receipt = $job->checkingReceipt();
        try {
            $result = $receipt
                ? $this->transport->receipt((string) $job->ticketId)
                : $this->transport->send($this->tokens->decrypt($device->encryptedToken), $job->notificationId);
        } catch (Throwable) {
            $result = new PushTransportResult($receipt ? 'PENDING' : PushJob::UNKNOWN, $job->ticketId, 'TRANSPORT_UNCERTAIN');
        }

        [$state, $due] = self::outcome($result->state, $receipt, $job->attempts, $at);
        $this->finish($job, $state, $due, $result->ticketId ?? $job->ticketId, $result->errorCode);
    }

    /** @return array{string, DateTimeImmutable} the stored state and when the job is next due */
    private static function outcome(string $result, bool $receipt, int $attempts, DateTimeImmutable $at): array
    {
        return match ($result) {
            'ACCEPTED', 'PENDING' => [PushJob::TICKET, $at->modify('+'.PushDeliveryPolicy::RECEIPT_DELAY_SECONDS.' seconds')],
            PushJob::RETRY => [
                $receipt ? PushJob::TICKET : (PushDeliveryPolicy::retryable($attempts) ? PushJob::RETRY : PushJob::FAILED),
                PushDeliveryPolicy::retryAt($at, $attempts),
            ],
            default => [$result, $at],
        };
    }

    private function stillAllowed(PushDevice $device, DateTimeImmutable $at): bool
    {
        if ($device->projectId !== $this->settings->projectId() || !$this->accounts->active($device->accountId)) {
            return false;
        }
        $session = $this->sessions->findById($device->sessionId);

        return null !== $session && $session->accountId === $device->accountId && $session->expiresAt > $at;
    }

    private function finish(PushJob $job, string $state, DateTimeImmutable $due, ?string $ticket, string $error): void
    {
        $this->transactions->execute(function () use ($job, $state, $due, $ticket, $error): void {
            $this->queue->finish($job, $state, $due, $ticket, $error, $this->clock->now());
        });
    }

    private function configured(): bool
    {
        return $this->settings->enabled() && $this->tokens->available();
    }
}
