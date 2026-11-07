<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetGiftAcknowledgement;

use App\Giving\Application\Contract\AcknowledgementReader;
use App\Giving\Application\Contract\AcknowledgementView;
use App\Giving\Application\GivingAccess;

/** The gift's acknowledgement, or null when none was issued (also for an unknown gift, as in Java). */
final readonly class GetGiftAcknowledgementHandler
{
    public function __construct(
        private GivingAccess $access,
        private AcknowledgementReader $reader,
    ) {
    }

    public function handle(GetGiftAcknowledgementQuery $query): ?AcknowledgementView
    {
        $this->access->read();

        return $this->reader->forGift($query->giftId);
    }
}
