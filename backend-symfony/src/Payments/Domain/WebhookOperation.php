<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use DateTimeImmutable;

/** A Yoco webhook registration or removal tracked across its provider call (STARTED, UNKNOWN, READY, APPLIED, CLOSED). */
final readonly class WebhookOperation
{
    public function __construct(
        public string $id,
        public string $revisionId,
        public string $mode,
        public string $kind,
        public ?string $targetId,
        public string $backendUrl,
        public string $requestHash,
        public string $actorId,
        public string $status,
        public ?string $webhookId,
        public ?string $encryptedSecret,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /** @return array{id: string, status: string} */
    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'status' => $this->status];
    }
}
