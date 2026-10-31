<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ProfilePayload
{
    public function __construct(#[Assert\Uuid] public string $personId, #[Assert\All(constraints: [new Assert\Type('string'), new Assert\Uuid()])] public array $approvedRoleIds, public array $availability, public array $qualifications = [], public ?string $qualificationsValidUntil = null, public ?string $screeningValidUntil = null, public bool $active = true)
    {
    }
}
