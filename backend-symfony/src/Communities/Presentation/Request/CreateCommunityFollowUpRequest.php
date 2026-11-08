<?php

declare(strict_types=1);

namespace App\Communities\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCommunityFollowUpRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $personId = null,
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 2000)]
        public ?string $note = null,
        #[Assert\NotNull, Assert\Date]
        public ?string $dueOn = null,
    ) {
    }
}
