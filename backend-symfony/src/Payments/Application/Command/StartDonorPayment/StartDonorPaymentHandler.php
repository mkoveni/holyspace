<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\StartDonorPayment;

use App\Giving\Application\Contract\DonorIdentity;
use App\Payments\Application\Command\StartPayment\StartPaymentCommand;
use App\Payments\Application\Command\StartPayment\StartPaymentHandler;
use App\Shared\Application\CommandHandler;

/** Donor self-service checkout: the donor is resolved server side from the caller's verified link, never the request. */
final readonly class StartDonorPaymentHandler implements CommandHandler
{
    public function __construct(private DonorIdentity $donors, private StartPaymentHandler $start)
    {
    }

    public function handle(StartDonorPaymentCommand $c): string
    {
        $donor = $this->donors->requireOwn($c->actorId, null);

        return $this->start->handle(new StartPaymentCommand(
            $c->actorId,
            $c->requestId,
            $donor,
            $c->fundId,
            $c->amountMinor,
            $c->provider,
            $c->recurring,
            true
        ));
    }
}
