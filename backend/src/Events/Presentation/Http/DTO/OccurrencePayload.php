<?php

declare(strict_types=1);

namespace App\Events\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class OccurrencePayload
{
    public function __construct(
        #[Assert\NotBlank] public string $startsAt,
        #[Assert\NotBlank] public string $endsAt,
        #[Assert\NotBlank] #[Assert\Length(max: 255)] public string $location,
        #[Assert\Choice(choices: ['scheduled', 'cancelled'])] public string $status = 'scheduled',
    ) {
    }
}
