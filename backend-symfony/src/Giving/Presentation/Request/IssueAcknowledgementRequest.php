<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class IssueAcknowledgementRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $requestId = null,
    ) {
    }
}
