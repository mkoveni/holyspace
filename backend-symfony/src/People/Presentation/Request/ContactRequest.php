<?php

declare(strict_types=1);

namespace App\People\Presentation\Request;

use App\People\Domain\ValueObject\ContactKind;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ContactRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull]
        public ?ContactKind $kind = null,
        public string $value = '',
        public bool $primary = false,
    ) {
    }
}
