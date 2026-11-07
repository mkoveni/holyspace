<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

/** Read side of acknowledgements (Java `AcknowledgementReader`). Reading never issues one. */
interface AcknowledgementReader
{
    public function get(string $id): ?AcknowledgementView;

    public function forGift(string $giftId): ?AcknowledgementView;
}
