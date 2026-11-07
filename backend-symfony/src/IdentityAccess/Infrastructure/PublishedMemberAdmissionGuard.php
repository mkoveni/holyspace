<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure;

use App\IdentityAccess\Application\Contract\MemberAdmission;
use App\IdentityAccess\Application\Contract\MemberAdmissionGuard;
use App\IdentityAccess\Domain\Repository\MemberLinkRepository;

final readonly class PublishedMemberAdmissionGuard implements MemberAdmissionGuard
{
    public function __construct(private MemberLinkRepository $links)
    {
    }

    public function lock(string $accountId): ?MemberAdmission
    {
        $link = $this->links->lock($accountId);

        return null === $link || !$link->active ? null : new MemberAdmission($link->personId, $link->version);
    }
}
