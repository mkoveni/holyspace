<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\DTO;

final readonly class ErrorResponse
{
    public function __construct(
        public string $message,
    ) {
    }
}
