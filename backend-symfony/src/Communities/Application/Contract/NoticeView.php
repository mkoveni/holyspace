<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

/** An approved notice without its evidence (Java `CommunityReader.Notice`). */
final readonly class NoticeView
{
    public function __construct(
        public string $id,
        public string $communityId,
        public string $membershipId,
        public string $contactId,
        public string $requestHash,
        public string $reviewHash,
        public string $actorId,
        public string $deliveryId,
    ) {
    }
}
