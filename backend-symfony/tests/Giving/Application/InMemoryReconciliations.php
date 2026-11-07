<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Domain\GivingException;
use App\Giving\Domain\Reconciliation;
use App\Giving\Domain\ReconciliationReview;
use App\Giving\Domain\Repository\ReconciliationRepository;

/** In-memory test double: no database. Logs the batch lock to the shared LockLog; the reversed amount is set by the test. */
final class InMemoryReconciliations implements ReconciliationRepository
{
    /** @var array<string, Reconciliation> */
    public array $batches = [];
    /** @var array<string, string> */
    public array $hashes = [];
    /** @var array<string, ReconciliationReview> */
    public array $reviews = [];
    /** @var array<string, string> */
    public array $reviewHashes = [];
    /** @var array<string, int> */
    public array $reversed = [];

    public function __construct(private readonly LockLog $log = new LockLog())
    {
    }

    public function createdBy(string $requestId, string $hash): ?string
    {
        return self::request($this->hashes, $requestId, $hash);
    }

    public function reviewedBy(string $requestId, string $hash): ?string
    {
        return self::request($this->reviewHashes, $requestId, $hash);
    }

    public function insert(Reconciliation $batch, string $hash): void
    {
        $this->batches[$batch->id] = $batch;
        $this->hashes[$batch->id] = $hash;
    }

    public function find(string $id): ?Reconciliation
    {
        return $this->batches[$id] ?? null;
    }

    public function lock(string $id): void
    {
        $this->log->locks[] = 'batch:'.$id;
        if (!isset($this->batches[$id])) {
            throw GivingException::missing();
        }
    }

    public function version(string $batchId): int
    {
        $version = 0;
        foreach ($this->reviews as $review) {
            if ($review->batchId === $batchId) {
                $version = max($version, $review->version);
            }
        }

        return $version;
    }

    public function reversedMinor(string $batchId): int
    {
        return $this->reversed[$batchId] ?? 0;
    }

    public function insertReview(ReconciliationReview $review, string $hash): void
    {
        $this->reviews[$review->id] = $review;
        $this->reviewHashes[$review->id] = $hash;
    }

    public function reconciled(string $giftId): bool
    {
        foreach ($this->batches as $batch) {
            if (\in_array($giftId, $batch->giftIds, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, string> $hashes */
    private static function request(array $hashes, string $id, string $hash): ?string
    {
        if (!isset($hashes[$id])) {
            return null;
        }
        if ($hashes[$id] !== $hash) {
            throw GivingException::conflict('This request identifier was used for different details.');
        }

        return $id;
    }
}
