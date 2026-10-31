<?php

declare(strict_types=1);

namespace App\People\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PersonSearchFilter
{
    public function __construct(
        #[Assert\Length(max: 120)] public string $q = '',
        #[Assert\Range(min: 1, max: 50)] public int $limit = 20,
        #[Assert\Count(max: 50)]
        #[Assert\All(constraints: [new Assert\Type('string'), new Assert\Uuid()])]
        public array $excludeIds = [],
        #[Assert\Count(max: 50)]
        #[Assert\All(constraints: [new Assert\Type('string'), new Assert\Uuid()])]
        public array $ids = [],
    ) {
    }
}
