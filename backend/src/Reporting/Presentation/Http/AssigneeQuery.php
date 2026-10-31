<?php

declare(strict_types=1);

namespace App\Reporting\Presentation\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AssigneeQuery
{
    public function __construct(#[Assert\Uuid] public ?string $segmentId = null, #[Assert\Uuid] public ?string $taskId = null)
    {
    }
}
