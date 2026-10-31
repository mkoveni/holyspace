<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ClassPayload
{
    public function __construct(#[Assert\Uuid] public string $programmeId, #[Assert\NotBlank] public string $name, public int $minAge, public int $maxAge, public int $capacity)
    {
    }
}
