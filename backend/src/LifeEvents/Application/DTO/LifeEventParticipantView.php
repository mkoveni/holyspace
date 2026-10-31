<?php

declare(strict_types=1);

namespace App\LifeEvents\Application\DTO;

final readonly class LifeEventParticipantView
{
    public function __construct(
        public string $personId,
        public string $role,
    ) {
    }
}
