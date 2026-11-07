<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetGift;

use App\Giving\Application\Contract\GiftDetail;
use App\Giving\Application\Contract\GivingReader;
use App\Giving\Application\GivingAccess;
use App\Giving\Domain\GivingException;

final readonly class GetGiftHandler
{
    public function __construct(
        private GivingAccess $access,
        private GivingReader $reader,
    ) {
    }

    public function handle(GetGiftQuery $query): GiftDetail
    {
        $this->access->read();

        return $this->reader->gift($query->giftId) ?? throw GivingException::missing();
    }
}
