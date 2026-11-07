<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetMemberOffer;

final readonly class GetMemberOfferQuery
{
    public function __construct(public string $eventId)
    {
    }
}
