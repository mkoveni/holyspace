<?php

declare(strict_types=1);

namespace App\Communications\Domain;

use DateTimeImmutable;

/**
 * One approved email to one reviewed recipient. The content is frozen when it is queued: editing a template
 * later never changes it. Statuses: QUEUED, SCHEDULED, SENDING, SENT, DELIVERED, FAILED, UNKNOWN, CANCELLED, BLOCKED.
 */
final readonly class EmailDelivery
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

    public function waiting(): bool
    {
        return 'QUEUED' === $this->status || 'SCHEDULED' === $this->status;
    }

    public function transition(string $next, DateTimeImmutable $scheduled, DateTimeImmutable $now, string $error, bool $capture): self
    {
        return $this->copy(scheduledAt: $scheduled, status: $next, finishedAt: $now, errorCode: $error, captured: $capture, version: $this->version + 1);
    }

    /** Starts an attempt: only a due, waiting delivery can be claimed, and only one worker wins the row lock. */
    public function claim(DateTimeImmutable $now, string $attemptId): self
    {
        if (!$this->waiting() || $this->scheduledAt > $now) {
            throw CommunicationException::conflict('This delivery is not due.');
        }

        return $this->copy(status: 'SENDING', attempts: $this->attempts + 1, attemptId: $attemptId, startedAt: $now, clearFinished: true, errorCode: '', captured: false, version: $this->version + 1);
    }

    /** A fresh approval puts a failed or blocked delivery back in the queue, due now. */
    public function approveRetry(string $actor, DateTimeImmutable $now): self
    {
        return $this->copy(approvedBy: $actor, approvedAt: $now, scheduledAt: $now, status: 'QUEUED', finishedAt: $now, errorCode: '', captured: false, version: $this->version + 1);
    }

    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'status' => $this->status];
    }

    private function copy(
        ?string $approvedBy = null,
        ?DateTimeImmutable $approvedAt = null,
        ?DateTimeImmutable $scheduledAt = null,
        ?string $status = null,
        ?int $attempts = null,
        ?string $attemptId = null,
        ?DateTimeImmutable $startedAt = null,
        ?DateTimeImmutable $finishedAt = null,
        ?string $errorCode = null,
        ?bool $captured = null,
        ?int $version = null,
        bool $clearFinished = false,
    ): self {
        return new self(
            $this->id,
            $this->communicationId,
            $this->verificationId,
            $this->contactId,
            $this->recipient,
            $this->subject,
            $this->body,
            $this->reviewHash,
            $approvedBy ?? $this->approvedBy,
            $approvedAt ?? $this->approvedAt,
            $scheduledAt ?? $this->scheduledAt,
            $status ?? $this->status,
            $attempts ?? $this->attempts,
            $this->messageId,
            $attemptId ?? $this->attemptId,
            $startedAt ?? $this->startedAt,
            $clearFinished ? null : ($finishedAt ?? $this->finishedAt),
            $errorCode ?? $this->errorCode,
            $captured ?? $this->captured,
            $version ?? $this->version,
            $this->contentType,
            $this->templateKey,
            $this->templateVersion,
        );
    }
}
