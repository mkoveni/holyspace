<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

final readonly class CreateEventVenueRequest
{
    public function __construct(
        public string $name = '',
        public string $address = '',
        public bool $exclusive = false,
    ) {
    }
}
