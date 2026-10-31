<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ProgrammePayload
{
    public function __construct(#[Assert\NotBlank] public string $name)
    {
    }
}
