<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class InvitationPayload
{
    public function __construct(#[Assert\Uuid] public string $shiftId, #[Assert\Uuid] public string $personId, public string $expiresAt)
    {
    }
}
