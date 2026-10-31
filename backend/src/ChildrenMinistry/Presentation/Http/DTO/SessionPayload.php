<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SessionPayload
{
    public function __construct(#[Assert\Uuid] public string $classId, #[Assert\Uuid] public string $occurrenceId, #[Assert\Count(min: 1)] #[Assert\All(constraints: [new Assert\Type('string'), new Assert\Uuid()])] public array $staffPersonIds)
    {
    }
}
