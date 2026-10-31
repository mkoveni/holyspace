<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReportFilter
{
    public function __construct(#[Assert\Date] public string $from, #[Assert\Date] public string $to, #[Assert\Uuid] public ?string $donorId = null, #[Assert\Uuid] public ?string $fundId = null)
    {
    }
}
