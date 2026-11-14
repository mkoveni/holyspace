<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TicketScanRequest
{
    public function __construct(#[Assert\NotBlank] #[Assert\Length(max: 300)] public ?string $code = null)
    {
    }
}
