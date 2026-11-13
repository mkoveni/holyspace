<?php

declare(strict_types=1);

namespace App\Forms\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Answers keyed by question id. Shape is checked here; every id, option and range is checked by the domain. */
final readonly class SubmitRequest
{
    /** @param array<string, mixed>|null $answers */
    public function __construct(
        #[Assert\NotNull] public ?array $answers = null,
        #[Assert\NotNull] #[Assert\Positive] public ?int $formVersion = null,
        #[Assert\NotBlank] #[Assert\Length(max: 100)] public ?string $submissionKey = null,
        #[Assert\Length(max: 40)] public ?string $nickname = null,
        #[Assert\Length(max: 200)] public ?string $challenge = null,
        #[Assert\Length(max: 2048)] public ?string $humanToken = null,
        /** Honeypot: people never see this field. */
        #[Assert\Length(max: 200)] public ?string $website = null,
    ) {
    }
}
