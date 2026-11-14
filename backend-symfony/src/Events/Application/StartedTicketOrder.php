<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Payments\Application\Contract\HostedCheckout;

/** The outcome of starting an order: the access token the buyer keeps, and the hosted checkout when money is due. */
final readonly class StartedTicketOrder
{
    public function __construct(public string $orderId, public string $accessToken, public ?HostedCheckout $checkout, public string $status, public int $totalMinor)
    {
    }
}
