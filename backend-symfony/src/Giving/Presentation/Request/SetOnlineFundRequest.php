<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java's FundBody. */
final readonly class SetOnlineFundRequest
{
    public function __construct(
        public bool $enabled,
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
    ) {
    }
}
