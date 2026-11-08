<?php

declare(strict_types=1);

namespace App\Communities\Domain;

use DateTimeImmutable;

/**
 * An approved member notice: the request id, the reviewed content's hash and the delivery queued for it. Approval is
 * final; Communications asks Communities again before every send.
 */
final readonly class CommunityNotice
{
    private function __construct(
        public string $id,
        public string $communityId,
        public string $membershipId,
        public string $contactId,
        public string $requestHash,
        public string $reviewHash,
        public string $evidence,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
        public string $deliveryId,
    ) {
    }

    public static function approve(string $requestId, string $communityId, string $membershipId, string $contactId, string $requestHash, string $reviewHash, string $evidence, string $actorId, DateTimeImmutable $now, string $deliveryId): self
    {
        return new self($requestId, $communityId, $membershipId, $contactId, $requestHash, $reviewHash, CommunityRules::text($evidence, 500), $actorId, $now, $deliveryId);
    }

    /** Evidence stays out of logs. */
    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'deliveryId' => $this->deliveryId];
    }
}
