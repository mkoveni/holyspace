<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MemberAccessPayload
{
    public function __construct(#[Assert\Uuid] #[Assert\NotBlank] public string $personId, #[Assert\Uuid] public ?string $expectedPersonId)
    {
    }
}
