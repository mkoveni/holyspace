<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation\Request;

use App\ChildrenMinistry\Sessions\Application\SessionSeriesDraft;
use App\Shared\Presentation\Json;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SessionSeriesDraftRequest
{
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 120)]
        public ?string $name = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $sourceSessionId = null,
        #[Assert\PositiveOrZero]
        public int $sourceVersion = 0,
        #[Assert\NotNull, Assert\Valid]
        public ?RecurrenceRequest $recurrence = null,
    ) {
    }

    public function toDraft(): SessionSeriesDraft
    {
        return new SessionSeriesDraft((string) $this->name, Json::uuid((string) $this->sourceSessionId), $this->sourceVersion, ($this->recurrence ?? new RecurrenceRequest())->toRecurrence());
    }
}
