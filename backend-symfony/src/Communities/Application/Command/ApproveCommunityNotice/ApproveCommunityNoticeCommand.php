<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\ApproveCommunityNotice;

use App\Shared\Application\Command;

/** @implements Command<string> the delivery id */
final readonly class ApproveCommunityNoticeCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $requestId,
        public string $membershipId,
        public string $contactId,
        public ?string $message,
        public ?string $reviewHash,
        public ?string $evidence,
    ) {
    }

    /** Message and evidence stay out of logs. */
    public function __debugInfo(): array
    {
        return ['membershipId' => $this->membershipId, 'requestId' => $this->requestId];
    }
}
