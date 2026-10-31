<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class FundPayload
{
    public function __construct(#[Assert\NotBlank] public string $name)
    {
    }
}
