<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PledgeStatusPayload
{
    public function __construct(#[Assert\Choice(choices: ['open', 'fulfilled', 'cancelled'])] public string $status)
    {
    }
}
