<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

final readonly class DonorConfirmation
{
    public function __construct(public string $subject, public string $html)
    {
    }
}
