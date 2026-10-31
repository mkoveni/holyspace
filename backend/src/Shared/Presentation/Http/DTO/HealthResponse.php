<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\DTO;

final readonly class HealthResponse
{
    public function __construct(
        public string $status,
        public string $application,
    ) {
    }
}
