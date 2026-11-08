<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RoleDraftRequest
{
    /** @param list<string|null>|null $requiredCredentials */
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 100)]
        public ?string $name = null,
        #[Assert\NotBlank(normalizer: 'trim')]
        public ?string $childrenRole = null,
        #[Assert\NotNull, Assert\Count(max: 30), Assert\All([new Assert\Type('string'), new Assert\Uuid()])]
        public ?array $requiredCredentials = null,
    ) {
    }
}
