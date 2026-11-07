<?php

declare(strict_types=1);

namespace App\People\Presentation\Request;

final readonly class CreateHouseholdRequest
{
    public function __construct(
        public string $name = '',
    ) {
    }
}
