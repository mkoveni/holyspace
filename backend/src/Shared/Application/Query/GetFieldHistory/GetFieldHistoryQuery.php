<?php

declare(strict_types=1);

namespace App\Shared\Application\Query\GetFieldHistory;

use App\Shared\Application\CQRS\Query;

final readonly class GetFieldHistoryQuery implements Query
{
    public function __construct(
        public string $context,
        public string $id,
        public int $limit = 50,
        public ?int $before = null,
    ) {
    }
}
