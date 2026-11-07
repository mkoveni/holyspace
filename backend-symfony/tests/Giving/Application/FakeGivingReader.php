<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Application\Contract\FundPage;
use App\Giving\Application\Contract\GiftDetail;
use App\Giving\Application\Contract\GiftFilter;
use App\Giving\Application\Contract\GiftPage;
use App\Giving\Application\Contract\GivingReader;
use App\Giving\Application\Contract\GivingTotal;

/** In-memory test double: records each query and returns only the gift details a test seeds. */
final class FakeGivingReader implements GivingReader
{
    /** @var list<array{bool, string, int, int}> */
    public array $calls = [];
    /** @var list<GiftFilter|string> */
    public array $giftCalls = [];
    /** @var list<GiftFilter> */
    public array $totalCalls = [];
    /** @var list<GivingTotal> */
    public array $totals = [];
    /** @var array<string, GiftDetail> */
    public array $details = [];

    public function funds(bool $archived, string $search, int $limit, int $offset): FundPage
    {
        $this->calls[] = [$archived, $search, $limit, $offset];

        return new FundPage([], 0);
    }

    public function gifts(GiftFilter $filter): GiftPage
    {
        $this->giftCalls[] = $filter;

        return new GiftPage([], 0);
    }

    public function gift(string $id): ?GiftDetail
    {
        $this->giftCalls[] = $id;

        return $this->details[$id] ?? null;
    }

    public function totals(GiftFilter $filter): array
    {
        $this->totalCalls[] = $filter;

        return $this->totals;
    }
}
