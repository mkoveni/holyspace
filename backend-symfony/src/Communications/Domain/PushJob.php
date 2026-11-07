<?php

declare(strict_types=1);

namespace App\Communications\Domain;

use DateTimeImmutable;

/**
 * One claimed push job: a notice aimed at one device, pinned to the device revision and topic preference version it
 * was queued for, so a later opt-out, revocation or token change cancels it instead of sending it.
 */
final readonly class PushJob
{
    public const string QUEUED = 'QUEUED';
    public const string SENDING = 'SENDING';
    public const string RETRY = 'RETRY';
    public const string TICKET = 'TICKET';
    public const string CHECKING = 'CHECKING';
    public const string PROVIDER_ACCEPTED = 'PROVIDER_ACCEPTED';
    public const string FAILED = 'FAILED';
    public const string UNKNOWN = 'UNKNOWN';
    public const string CANCELLED = 'CANCELLED';

    public function __construct(
        public string $id,
        public string $notificationId,
        public string $deviceId,
        public int $deviceRevision,
        public string $topic,
        public int $preferenceVersion,
        public string $status,
        public int $attempts,
        public DateTimeImmutable $createdAt,
        public ?string $claimId,
        public ?string $ticketId,
    ) {
    }

    /** A claimed job that already has a provider ticket is checking its receipt, not sending. */
    public function checkingReceipt(): bool
    {
        return self::CHECKING === $this->status;
    }
}
