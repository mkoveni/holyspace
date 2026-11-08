<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

use App\Shared\Domain\CalendarDate;

final readonly class FollowUpView
{
    public function __construct(
        public string $id,
        public string $communityId,
        public string $personId,
        public string $name,
        public string $note,
        public CalendarDate $dueOn,
        public string $status,
        public int $version,
    ) {
    }
}
