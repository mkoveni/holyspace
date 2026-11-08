<?php

declare(strict_types=1);

namespace App\Communities\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCommunityRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 150)]
        public ?string $kind = null,
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 150)]
        public ?string $name = null,
        #[Assert\Length(max: 5000)]
        public ?string $description = null,
        #[Assert\Uuid]
        public ?string $parentId = null,
        #[Assert\Length(max: 150)]
        public ?string $campus = null,
    ) {
    }
}
