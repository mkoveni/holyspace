<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Domain\Gift;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Repository\GiftRepository;
use App\Giving\Domain\Reversal;

/** In-memory test double: no database. One reversal per gift, as the unique gift_id column enforces. */
final class InMemoryGifts implements GiftRepository
{
    /** @var array<string, Gift> */
    public array $gifts = [];
    /** @var array<string, string> */
    public array $hashes = [];
    /** @var array<string, Reversal> */
    public array $reversals = [];
    /** @var array<string, string> */
    public array $reversalHashes = [];
    /** @var array<string, int> */
    public array $refunds = [];

    public function __construct(private readonly LockLog $log = new LockLog())
    {
    }

    public function findForUpdate(string $id): ?Gift
    {
        $this->log->locks[] = 'gift:'.$id;

        return $this->gifts[$id] ?? null;
    }

    public function postedBy(string $requestId, string $hash): ?string
    {
        return self::request($this->hashes, $requestId, $hash);
    }

    public function insert(Gift $gift, string $hash): void
    {
        $this->gifts[$gift->id] = $gift;
        $this->hashes[$gift->id] = $hash;
    }

    public function reversedBy(string $requestId, string $hash): ?string
    {
        return self::request($this->reversalHashes, $requestId, $hash);
    }

    public function hasReversal(string $giftId): bool
    {
        foreach ($this->reversals as $reversal) {
            if ($reversal->giftId === $giftId) {
                return true;
            }
        }

        return false;
    }

    public function refunded(string $giftId): int
    {
        return $this->refunds[$giftId] ?? 0;
    }

    public function insertReversal(Reversal $reversal, string $hash): void
    {
        $this->reversals[$reversal->id] = $reversal;
        $this->reversalHashes[$reversal->id] = $hash;
    }

    /** @param array<string, string> $hashes */
    private static function request(array $hashes, string $id, string $hash): ?string
    {
        if (!isset($hashes[$id])) {
            return null;
        }
        if ($hashes[$id] !== $hash) {
            throw GivingException::conflict('This request identifier was already used for different details.');
        }

        return $id;
    }
}
