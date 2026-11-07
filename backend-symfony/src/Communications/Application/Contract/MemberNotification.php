<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

use DateTimeImmutable;

/** One inbox notice as its member sees it: fixed wording and destination, no account or source details. */
final readonly class MemberNotification
{
    public function __construct(
        public string $id,
        public string $topic,
        public string $title,
        public string $message,
        public string $destination,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $readAt,
    ) {
    }
}
