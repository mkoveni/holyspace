<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CheckInPayload
{
    public function __construct(#[Assert\Uuid] public string $sessionId, #[Assert\Uuid] public string $childId, #[Assert\Uuid] public string $staffPersonId)
    {
    }
}
