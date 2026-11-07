<?php

declare(strict_types=1);

namespace App\Communications\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PreviewTemplateRequest
{
    /**
     * @param list<string>|null          $variables
     * @param array<string, string>|null $values
     */
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 200)]
        public ?string $subject = null,
        #[Assert\NotBlank, Assert\Length(max: 50000)]
        public ?string $html = null,
        #[Assert\NotNull, Assert\Count(max: 30), Assert\All([new Assert\Type('string')])]
        public ?array $variables = null,
        #[Assert\NotNull, Assert\Count(max: 30), Assert\All([new Assert\Type('string')])]
        public ?array $values = null,
    ) {
    }
}
