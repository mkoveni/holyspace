<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Application;

use App\IdentityAccess\Domain\MemberLink;
use App\IdentityAccess\Domain\Repository\MemberLinkRepository;

final class FakeLinks implements MemberLinkRepository
{
    /** @var array<string, MemberLink> */
    public array $links = [];
    /** @var list<string> */
    public array $evidence = [];

    public function __construct(private readonly LockLog $log)
    {
    }

    public function lock(string $accountId): ?MemberLink
    {
        $this->log->entries[] = 'link';

        return $this->links[$accountId] ?? null;
    }

    public function save(MemberLink $link, string $evidence): void
    {
        $this->links[$link->accountId] = $link;
        $this->evidence[] = $evidence;
    }
}
