<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class OccurrenceChangeRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Valid]
        public ?OccurrenceChangeDraftRequest $draft = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 1000)]
        public string $reason = '',
        public ?string $reviewHash = null,
    ) {
    }
}
