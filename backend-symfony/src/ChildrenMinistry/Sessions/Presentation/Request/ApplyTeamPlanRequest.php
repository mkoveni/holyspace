<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ApplyTeamPlanRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Valid]
        public ?TeamPlanDraftRequest $draft = null,
        #[Assert\NotBlank, Assert\Length(max: 500)]
        public ?string $reason = null,
    ) {
    }
}
