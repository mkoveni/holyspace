<?php

declare(strict_types=1);

namespace App\Events\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateEventPayload
{
    public function __construct(
        #[Assert\NotBlank] #[Assert\Length(max: 200)] public string $title,
        #[Assert\Choice(choices: ['service', 'meeting', 'conference', 'outreach', 'class', 'other'])] public string $type,
        #[Assert\NotBlank] public string $startsAt,
        #[Assert\NotBlank] public string $endsAt,
        #[Assert\NotBlank] #[Assert\Length(max: 255)] public string $location,
        #[Assert\Timezone] public string $timezone = 'Africa/Johannesburg',
        #[Assert\Choice(choices: ['once', 'weekly'])] public string $recurrence = 'once',
        #[Assert\Range(min: 1, max: 52)] public int $occurrenceCount = 1,
        #[Assert\Length(max: 5000)] public ?string $description = null,
    ) {
    }
}
