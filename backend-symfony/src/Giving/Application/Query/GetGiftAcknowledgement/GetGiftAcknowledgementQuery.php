<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetGiftAcknowledgement;

final readonly class GetGiftAcknowledgementQuery
{
    public function __construct(public string $giftId)
    {
    }
}
