<?php

declare(strict_types=1);

namespace App\Giving\Domain\Repository;

use App\Giving\Domain\Reconciliation;
use App\Giving\Domain\ReconciliationReview;

/** Insert-only reconciliation batches and reviews. Unique and integrity violations become an evidence conflict. */
interface ReconciliationRepository
{
    /**
     * The batch id when one was already created with this request id and hash, null when none was.
     * Throws a conflict when the id was used for different details.
     *
     * @phpstan-impure the create handler checks again after locking the gifts
     */
    public function createdBy(string $requestId, string $hash): ?string;

    /** Like createdBy, for a review request id. */
    public function reviewedBy(string $requestId, string $hash): ?string;

    /** Inserts the batch and its items, then checks the snapshot trigger at once instead of at commit. */
    public function insert(Reconciliation $batch, string $hash): void;

    /** The batch without a lock; its gift ids are ordered by id. */
    public function find(string $id): ?Reconciliation;

    /** Locks the batch row until the transaction ends; throws missing when it is gone. */
    public function lock(string $id): void;

    /** The batch's review version: the latest review's version, 0 before the first review. */
    public function version(string $batchId): int;

    /** The batch's current reversed amount: whole reversed gifts plus provider refunds of the others. */
    public function reversedMinor(string $batchId): int;

    public function insertReview(ReconciliationReview $review, string $hash): void;

    /** Whether the gift already belongs to a batch (a gift may be reconciled once). */
    public function reconciled(string $giftId): bool;
}
