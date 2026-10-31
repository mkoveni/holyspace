<?php

declare(strict_types=1);

namespace App\Reporting\Presentation\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SegmentPayload
{
    public function __construct(#[Assert\NotBlank] #[Assert\Length(max: 200)] public string $name, public array $filters, #[Assert\Positive] public ?int $expectedVersion = null)
    {
    }
}
