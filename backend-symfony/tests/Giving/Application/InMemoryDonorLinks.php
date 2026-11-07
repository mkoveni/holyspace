<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Application\Contract\DonorLinkReader;
use App\Giving\Application\Contract\DonorLinkView;
use App\Giving\Domain\DonorLink;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Repository\DonorLinkRepository;
use DateTimeImmutable;

/** In-memory test double for both sides of donor links: one link per account and per person, as the table enforces. */
final class InMemoryDonorLinks implements DonorLinkRepository, DonorLinkReader
{
    /** @var array<string, DonorLink> */
    public array $links = [];
    /** @var list<string> "account:version:evidence" */
    public array $history = [];

    public function __construct(private readonly LockLog $log = new LockLog())
    {
    }

    public function add(string $accountId, string $personId, bool $active = true, int $version = 1): void
    {
        $this->links[$accountId] = DonorLink::reconstitute($accountId, $personId, $active, $version, 'staff', new DateTimeImmutable('2026-01-01T08:00:00Z'));
    }

    public function lock(string $accountId): ?DonorLink
    {
        $this->log->locks[] = 'link:'.$accountId;

        return $this->links[$accountId] ?? null;
    }

    public function save(DonorLink $link, string $evidence): void
    {
        foreach ($this->links as $other) {
            if ($other->accountId !== $link->accountId && $other->personId === $link->personId) {
                throw GivingException::evidenceConflict();
            }
        }
        $this->links[$link->accountId] = $link;
        $this->history[] = $link->accountId.':'.$link->version.':'.$evidence;
    }

    public function link(string $accountId): ?DonorLinkView
    {
        $link = $this->links[$accountId] ?? null;

        return null === $link ? null : self::view($link);
    }

    public function links(int $limit, int $offset): array
    {
        return array_map(self::view(...), \array_slice(array_values($this->links), $offset, $limit));
    }

    private static function view(DonorLink $link): DonorLinkView
    {
        return new DonorLinkView($link->accountId, $link->personId, $link->active, $link->version, $link->verifiedBy, $link->verifiedAt);
    }
}
