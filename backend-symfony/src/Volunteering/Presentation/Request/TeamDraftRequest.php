<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TeamDraftRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 150)]
        public ?string $name = null,
    ) {
    }
}
