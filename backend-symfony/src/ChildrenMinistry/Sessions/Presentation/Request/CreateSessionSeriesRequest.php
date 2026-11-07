<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateSessionSeriesRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotNull, Assert\Valid]
        public ?SessionSeriesDraftRequest $draft = null,
    ) {
    }
}
