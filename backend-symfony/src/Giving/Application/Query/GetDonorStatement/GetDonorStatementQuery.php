<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetDonorStatement;

use App\Shared\Domain\CalendarDate;

final readonly class GetDonorStatementQuery
{
    public function __construct(
        public ?string $donorId,
        public ?CalendarDate $from,
        public ?CalendarDate $to,
    ) {
    }
}
