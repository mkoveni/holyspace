<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\DTO;

final readonly class CreatedResponse
{
    public function __construct(
        public ?string $id,
    ) {
    }
}
