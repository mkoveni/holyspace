<?php

declare(strict_types=1);

namespace App\People\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetFamilyPrimaryContactPayload
{
    public function __construct(#[Assert\Uuid] public ?string $personId)
    {
    }
}
