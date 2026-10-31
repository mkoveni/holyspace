<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReminderPayload
{
    public function __construct(#[Assert\Choice(choices: ['email', 'sms'])] public string $channel, #[Assert\NotBlank, Assert\Length(max: 100)] public string $reference)
    {
    }
}
