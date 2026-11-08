<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ReviewCommunityNotice;

final readonly class ReviewCommunityNoticeQuery
{
    public function __construct(public string $membershipId, public string $contactId, public ?string $message)
    {
    }

    /** The message stays out of logs. */
    public function __debugInfo(): array
    {
        return ['membershipId' => $this->membershipId];
    }
}
