<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateChurchEventRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotNull]
        #[Assert\Valid]
        public ?ScheduleDraftRequest $schedule = null,
        public string $title = '',
        public string $type = '',
        public string $description = '',
        public ?string $previewHash = null,
    ) {
    }
}
