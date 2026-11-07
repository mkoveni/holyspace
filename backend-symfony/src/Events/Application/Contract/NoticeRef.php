<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

/** One approved notice for an occurrence's registrations; its delivery is identified by the same id. */
final readonly class NoticeRef
{
    public function __construct(
        public string $id,
        public string $registrationId,
        public string $purpose,
        public int $registrationVersion,
        public int $scheduleRevision,
        public int $eventVersion,
    ) {
    }
}
