<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AssignmentStatusPayload
{
    public function __construct(#[Assert\Choice(choices: ['assigned', 'needs_review', 'declined', 'cancelled'])] public string $status)
    {
    }
}
