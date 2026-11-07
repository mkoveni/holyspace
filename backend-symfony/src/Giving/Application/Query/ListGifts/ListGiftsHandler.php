<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\ListGifts;

use App\Giving\Application\Contract\GiftPage;
use App\Giving\Application\Contract\GivingReader;
use App\Giving\Application\GivingAccess;
use App\Giving\Application\GivingFilters;

final readonly class ListGiftsHandler
{
    public function __construct(
        private GivingAccess $access,
        private GivingReader $reader,
    ) {
    }

    public function handle(ListGiftsQuery $query): GiftPage
    {
        $this->access->read();
        GivingFilters::validate($query->filter);

        return $this->reader->gifts($query->filter);
    }
}
