<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Domain\Fund;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Repository\FundRepository;

/** In-memory test double: no database. Names are unique ignoring case and surrounding spaces, as in the schema. */
final class InMemoryFunds implements FundRepository
{
    /** @var array<string, Fund> */
    public array $funds = [];
    /** @var array<string, string> */
    public array $hashes = [];

    /** @var array<string, bool> */
    public array $online = [];

    public function __construct(public readonly LockLog $log = new LockLog())
    {
    }

    public function findForUpdate(string $id): ?Fund
    {
        $this->log->locks[] = 'fund:'.$id;

        return $this->funds[$id] ?? null;
    }

    public function createdBy(string $requestId, string $hash): ?string
    {
        if (!isset($this->hashes[$requestId])) {
            return null;
        }
        if ($this->hashes[$requestId] !== $hash) {
            throw GivingException::conflict('This request identifier was already used for different details.');
        }

        return $requestId;
    }

    public function insert(Fund $fund, string $hash): bool
    {
        if (isset($this->funds[$fund->id]) || $this->nameTaken($fund)) {
            return false;
        }
        $this->funds[$fund->id] = $fund;
        $this->hashes[$fund->id] = $hash;

        return true;
    }

    public function save(Fund $fund): void
    {
        if ($this->nameTaken($fund)) {
            throw GivingException::evidenceConflict();
        }
        $this->funds[$fund->id] = $fund;
    }

    public function offerOnline(string $fundId, bool $enabled): void
    {
        $this->online[$fundId] = $enabled;
    }

    private function nameTaken(Fund $fund): bool
    {
        foreach ($this->funds as $other) {
            if ($other->id !== $fund->id && strtolower($other->name) === strtolower($fund->name)) {
                return true;
            }
        }

        return false;
    }
}
