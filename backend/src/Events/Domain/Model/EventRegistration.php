<?php

declare(strict_types=1);

namespace App\Events\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Uuid;

final class EventRegistration extends WorkflowRecord
{
    public readonly string $queueToken;

    public function __construct(
        public readonly string $occurrenceId,
        public readonly string $personId,
        public readonly string $status = 'confirmed',
        string $queueToken = '',
        public readonly array $history = [],
        public readonly int $queuePosition = 0,
        public readonly ?string $paymentOrderId = null,
        public readonly ?string $paymentExpiresAt = null,
        string $id = '',
        string $createdAt = '',
        string $updatedAt = '',
        int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($occurrenceId);
        Input::id($personId);
        if (!in_array($status, ['confirmed', 'waitlisted', 'cancelled', 'pending_payment'], true)) {
            throw new InvalidArgumentException('Invalid registration status.');
        }
        if ('pending_payment' === $status && (null === $paymentOrderId || null === $paymentExpiresAt)) {
            throw new InvalidArgumentException('A pending payment requires an order and expiry.');
        }
        if (null !== $paymentOrderId) {
            Input::id($paymentOrderId);
        }
        if (null !== $paymentExpiresAt) {
            new DateTimeImmutable($paymentExpiresAt);
        }
        $this->queueToken = '' === $queueToken ? Uuid::v7()->toRfc4122() : $queueToken;
        Input::id($this->queueToken);
    }

    public function reserve(string $orderId, string $expires): self
    {
        return $this->changed(['status' => 'pending_payment', 'paymentOrderId' => $orderId, 'paymentExpiresAt' => $expires, 'queueToken' => Uuid::v7()->toRfc4122(), 'history' => [...$this->history, ['previousStatus' => $this->status, 'status' => 'pending_payment', 'reason' => 'A hosted ticket payment reserved a place.', 'changedAt' => Clock::get()->now()->format(DATE_ATOM)]]]);
    }

    public function transition(string $status, string $reason, bool $rejoin = false): self
    {
        Input::text($reason, 1000);

        return $this->changed(['status' => $status, 'paymentOrderId' => $rejoin ? null : $this->paymentOrderId, 'paymentExpiresAt' => $rejoin ? null : $this->paymentExpiresAt, 'queueToken' => $rejoin ? Uuid::v7()->toRfc4122() : $this->queueToken, 'history' => [...$this->history, ['previousStatus' => $this->status, 'status' => $status, 'reason' => $reason, 'changedAt' => Clock::get()->now()->format(DATE_ATOM)]]]);
    }
}
