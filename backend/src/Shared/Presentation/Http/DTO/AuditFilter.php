<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AuditFilter
{
    public function __construct(#[Assert\Range(min: 1, max: 100)] public int $limit = 25, #[Assert\Positive] public ?int $before = null)
    {
    }
}
