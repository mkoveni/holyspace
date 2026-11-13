<?php

declare(strict_types=1);

namespace App\Forms\Application\Contract;

final readonly class FormSummary
{
    public function __construct(
        public string $id,
        public string $title,
        public string $kind,
        public string $audience,
        public string $identity,
        public string $status,
        public int $responseCount,
        public int $version,
    ) {
    }
}
