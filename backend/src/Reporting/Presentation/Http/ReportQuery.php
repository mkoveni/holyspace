<?php

declare(strict_types=1);

namespace App\Reporting\Presentation\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReportQuery
{
    public function __construct(#[Assert\Date] public string $from, #[Assert\Date] public string $to)
    {
    }
}
