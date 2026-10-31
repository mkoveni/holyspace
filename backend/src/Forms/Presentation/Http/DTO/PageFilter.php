<?php

declare(strict_types=1);

namespace App\Forms\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PageFilter
{
    public function __construct(#[Assert\Uuid] public ?string $after = null, #[Assert\Range(min: 1, max: 100)] public int $limit = 50)
    {
    }
}
