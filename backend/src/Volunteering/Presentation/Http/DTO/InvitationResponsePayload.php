<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class InvitationResponsePayload
{
    public function __construct(#[Assert\Choice(choices: ['accepted', 'declined'])] public string $decision, #[Assert\Positive] public int $expectedVersion)
    {
    }
}
