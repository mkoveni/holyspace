<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\RetryPaymentPosting;

final readonly class PostingResult
{
    public function __construct(public bool $complete, public string $message)
    {
    }
}
