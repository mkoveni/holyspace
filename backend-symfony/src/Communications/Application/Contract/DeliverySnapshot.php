<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

use DateTimeImmutable;

/** A delivery as originating contexts see it: the approved message, its recipient and where sending stands. */
final readonly class DeliverySnapshot
{
    public function __construct(
        public string $id,
        public string $communicationId,
        public ?string $verificationId,
        public ?string $contactId,
        public string $recipient,
        public string $subject,
        public string $body,
        public string $reviewHash,
        public string $approvedBy,
        public DateTimeImmutable $approvedAt,
        public DateTimeImmutable $scheduledAt,
        public string $status,
        public int $attempts,
        public string $messageId,
        public ?string $attemptId,
        public ?DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $finishedAt,
        public string $errorCode,
        public bool $captured,
        public int $version,
        public string $contentType = 'text/plain',
        public ?string $templateKey = null,
        public ?int $templateVersion = null,
    ) {
    }

    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'status' => $this->status];
    }
}
