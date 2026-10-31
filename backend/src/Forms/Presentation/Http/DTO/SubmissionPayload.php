<?php

declare(strict_types=1);

namespace App\Forms\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SubmissionPayload
{
    public function __construct(#[Assert\Count(max: 20)] public array $answers, #[Assert\Positive] public int $formVersion, #[Assert\Uuid] public string $submissionKey, #[Assert\Length(max: 200)] public string $challenge, #[Assert\Length(max: 1000)] public string $website = '')
    {
    }
}
