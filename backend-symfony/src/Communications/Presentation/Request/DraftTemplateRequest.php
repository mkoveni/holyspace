<?php

declare(strict_types=1);

namespace App\Communications\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DraftTemplateRequest
{
    /** @param list<string>|null $variables */
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 100)]
        public ?string $key = null,
        #[Assert\NotBlank, Assert\Length(max: 150)]
        public ?string $name = null,
        #[Assert\NotBlank, Assert\Length(max: 200)]
        public ?string $subject = null,
        #[Assert\NotBlank, Assert\Length(max: 50000)]
        public ?string $html = null,
        #[Assert\NotNull, Assert\Count(max: 30), Assert\All([new Assert\Type('string')])]
        public ?array $variables = null,
    ) {
    }
}
