<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\ListFormsRecords;

use App\Shared\Application\CQRS\Query;

final readonly class ListFormsRecordsQuery implements Query
{
    public function __construct(
        public string $kind,
        public ?string $formId,
        public ?string $after,
        public int $limit,
    ) {
    }
}
