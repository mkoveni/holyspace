<?php

declare(strict_types=1);

namespace App\People\Presentation\Request;

use App\People\Domain\ValueObject\RelationshipType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateRelationshipRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $sourceId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $targetId = null,
        #[Assert\NotNull]
        public ?RelationshipType $type = null,
    ) {
    }
}
