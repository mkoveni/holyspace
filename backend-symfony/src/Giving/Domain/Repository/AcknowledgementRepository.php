<?php

declare(strict_types=1);

namespace App\Giving\Domain\Repository;

use App\Giving\Domain\Acknowledgement;

/** Insert-only acknowledgement evidence. Unique and integrity violations become an evidence conflict. */
interface AcknowledgementRepository
{
    /**
     * The acknowledgement id when one was already issued with this request id and hash, null when none was.
     * Throws a conflict when the id was used for different details.
     */
    public function issuedBy(string $requestId, string $hash): ?string;

    /** The id of the gift's acknowledgement, if it has one. */
    public function forGift(string $giftId): ?string;

    /** Inserts the header and lines, then checks the balance trigger at once instead of at commit. */
    public function insert(Acknowledgement $acknowledgement, string $hash): void;
}
