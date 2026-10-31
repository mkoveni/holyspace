<?php

declare(strict_types=1);

namespace App\Ministries\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MinistryPayload
{
    public function __construct(#[Assert\NotBlank] public string $name, public string $type = 'ministry', public ?string $description = null)
    {
    }
}
