<?php

declare(strict_types=1);

namespace App\Communications\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PushTopicPreferenceRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull]
        public ?bool $enabled = null,
    ) {
    }
}
