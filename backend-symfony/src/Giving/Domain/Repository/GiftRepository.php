<?php

declare(strict_types=1);

namespace App\Giving\Domain\Repository;

use App\Giving\Domain\Gift;
use App\Giving\Domain\Reversal;

/** Gifts and their reversals: insert-only evidence. Unique and integrity violations become an evidence conflict. */
interface GiftRepository
{
    /** Locks the gift row until the transaction ends. */
    public function findForUpdate(string $id): ?Gift;

    /**
     * The gift id when a gift with this request id was already posted with the same hash, null when none was.
     * Throws a conflict when the id was used for different details.
     */
    public function postedBy(string $requestId, string $hash): ?string;

    /** Inserts the header and allocations, then checks the balance trigger at once instead of at commit. */
    public function insert(Gift $gift, string $hash): void;

    /** Like postedBy, for a reversal request id. */
    public function reversedBy(string $requestId, string $hash): ?string;

    public function hasReversal(string $giftId): bool;

    public function insertReversal(Reversal $reversal, string $hash): void;
}
