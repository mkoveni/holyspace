<?php

declare(strict_types=1);

namespace App\Events\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class EventDetailsPayload
{
    public function __construct(
        #[Assert\NotBlank] #[Assert\Length(max: 200)] public string $title,
        #[Assert\Choice(choices: ['service', 'meeting', 'conference', 'outreach', 'class', 'other'])] public string $type,
        #[Assert\Length(max: 5000)] public ?string $description = null,
    ) {
    }
}
