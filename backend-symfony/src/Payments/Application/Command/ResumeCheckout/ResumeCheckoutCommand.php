<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\ResumeCheckout;

final readonly class ResumeCheckoutCommand
{
    /** $guest: the caller proved the public order's access token (the controller checked it); there is no account. */
    public function __construct(public string $actorId, public string $orderId, public bool $selfService = false, public bool $guest = false)
    {
    }
}
