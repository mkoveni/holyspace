<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PreviewEventScheduleRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Valid]
        public ?ScheduleDraftRequest $draft = null,
    ) {
    }
}
