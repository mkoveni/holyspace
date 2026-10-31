<?php

declare(strict_types=1);

namespace App\Ministries\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MeetingPayload
{
    public function __construct(#[Assert\Uuid] public string $occurrenceId)
    {
    }
}
