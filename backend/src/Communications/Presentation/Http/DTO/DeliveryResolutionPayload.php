<?php

declare(strict_types=1);

namespace App\Communications\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DeliveryResolutionPayload
{
    public function __construct(#[Assert\Choice(choices: ['delivered', 'failed'])] public string $status, #[Assert\NotBlank] #[Assert\Length(max: 500)] public string $note)
    {
    }
}
