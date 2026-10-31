<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RolePayload
{
    public function __construct(#[Assert\NotBlank] public string $name, public bool $requiresScreening = false, public array $requiredQualifications = [])
    {
    }
}
