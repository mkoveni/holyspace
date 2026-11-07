<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

/** Java `ListDonorAccessHandler.Result`: a page of links, the whole fund catalogue and whether more links exist. */
final readonly class DonorAccessPage
{
    /**
     * @param list<DonorAccessLink> $links
     * @param list<OnlineFund>      $funds
     */
    public function __construct(public array $links, public array $funds, public bool $hasMore)
    {
    }
}
