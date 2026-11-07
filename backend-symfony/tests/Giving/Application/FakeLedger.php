<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Application\Contract\GiftFilter;
use App\Giving\Application\Contract\GivingLedgerReader;
use App\Giving\Application\Contract\LedgerLine;

/** In-memory test double: returns fixed lines and records each filter and maximum it was asked for. */
final class FakeLedger implements GivingLedgerReader
{
    /** @var list<array{GiftFilter, int}> */
    public array $calls = [];

    /** @param list<LedgerLine> $lines */
    public function __construct(public array $lines = [])
    {
    }

    public function lines(GiftFilter $filter, int $maximum): array
    {
        $this->calls[] = [$filter, $maximum];

        return $this->lines;
    }
}
