<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Application\Contract\AcknowledgementReader;
use App\Giving\Application\Contract\AcknowledgementView;

/** In-memory test double: knows no acknowledgements and records each call. */
final class FakeAcknowledgementReader implements AcknowledgementReader
{
    /** @var list<string> */
    public array $calls = [];

    public function get(string $id): ?AcknowledgementView
    {
        $this->calls[] = 'get:'.$id;

        return null;
    }

    public function forGift(string $giftId): ?AcknowledgementView
    {
        $this->calls[] = 'gift:'.$giftId;

        return null;
    }
}
