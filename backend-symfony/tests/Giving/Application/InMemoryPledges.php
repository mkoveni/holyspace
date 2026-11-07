<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Domain\GivingException;
use App\Giving\Domain\Pledge;
use App\Giving\Domain\PledgeAssignment;
use App\Giving\Domain\Repository\PledgeRepository;

/** In-memory test double: no database. Saves enforce the previous-version rule, as the store's WHERE clause does. */
final class InMemoryPledges implements PledgeRepository
{
    /** @var array<string, Pledge> */
    public array $pledges = [];
    /** @var array<string, string> */
    public array $hashes = [];
    /** @var array<string, PledgeAssignment> */
    public array $assignments = [];
    /** @var array<string, string> */
    public array $assignmentHashes = [];
    /** @var list<string> gift ids treated as reversed by credited() */
    public array $reversedGifts = [];
    /** Unlocked assignment reads, so a test can see the handler reads again under its locks. */
    public int $assignmentReads = 0;

    public function __construct(private readonly LockLog $log = new LockLog())
    {
    }

    public function pledgedBy(string $requestId, string $hash): ?string
    {
        return self::request($this->hashes, $requestId, $hash);
    }

    public function insert(Pledge $pledge, string $hash): void
    {
        $this->pledges[$pledge->id] = $pledge;
        $this->hashes[$pledge->id] = $hash;
    }

    public function findForUpdate(string $id): ?Pledge
    {
        $this->log->locks[] = 'pledge:'.$id;

        return $this->pledges[$id] ?? null;
    }

    public function save(Pledge $pledge): void
    {
        if (($this->pledges[$pledge->id] ?? null)?->version !== $pledge->version - 1) {
            throw GivingException::conflict('The pledge changed. Refresh before trying again.');
        }
        $this->pledges[$pledge->id] = $pledge;
    }

    public function assignedBy(string $requestId, string $hash): ?string
    {
        return self::request($this->assignmentHashes, $requestId, $hash);
    }

    public function findAssignment(string $id): ?PledgeAssignment
    {
        ++$this->assignmentReads;

        return $this->assignments[$id] ?? null;
    }

    public function insertAssignment(PledgeAssignment $assignment, string $hash): void
    {
        $this->assignments[$assignment->id] = $assignment;
        $this->assignmentHashes[$assignment->id] = $hash;
    }

    public function saveAssignment(PledgeAssignment $assignment): void
    {
        if (($this->assignments[$assignment->id] ?? null)?->version !== $assignment->version - 1) {
            throw GivingException::conflict('This assignment changed.');
        }
        $this->assignments[$assignment->id] = $assignment;
    }

    public function credited(string $pledgeId): int
    {
        return $this->sum(fn (PledgeAssignment $a): bool => $a->pledgeId === $pledgeId && !\in_array($a->giftId, $this->reversedGifts, true));
    }

    public function assigned(string $giftId, string $fundId): int
    {
        return $this->sum(static fn (PledgeAssignment $a): bool => $a->giftId === $giftId && $a->fundId === $fundId);
    }

    /** @param callable(PledgeAssignment): bool $matches */
    private function sum(callable $matches): int
    {
        $sum = 0;
        foreach ($this->assignments as $assignment) {
            if (PledgeAssignment::ACTIVE === $assignment->status && $matches($assignment)) {
                $sum += $assignment->amountMinor;
            }
        }

        return $sum;
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
