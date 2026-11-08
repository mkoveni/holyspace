<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

/** Java `CommunityReader.Report`: roster counts per meeting. `expected` counts every roster row, guests included. */
final readonly class AttendanceReport
{
    public function __construct(
        public string $meetingId,
        public int $expected,
        public int $present,
        public int $absent,
        public int $excused,
        public int $unrecorded,
        public int $guests,
    ) {
    }
}
