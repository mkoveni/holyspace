<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

/** Read side of verified donor links (Java `DonorLinkReader`). */
interface DonorLinkReader
{
    public function link(string $accountId): ?DonorLinkView;

    /**
     * Links newest verification first, then by account id.
     *
     * @return list<DonorLinkView>
     */
    public function links(int $limit, int $offset): array;
}
