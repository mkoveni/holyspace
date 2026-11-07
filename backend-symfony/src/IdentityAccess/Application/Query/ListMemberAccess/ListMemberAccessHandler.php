<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\ListMemberAccess;

use App\IdentityAccess\Application\Contract\AccountNames;
use App\IdentityAccess\Application\Contract\MemberLinkReader;
use App\IdentityAccess\Application\Contract\MemberLinkSummary;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\IdentityAccess\Application\MemberAccessEntry;
use App\IdentityAccess\Application\MemberAccessPage;
use App\IdentityAccess\Domain\IdentityException;
use App\People\Application\Contract\PersonDirectory;

final readonly class ListMemberAccessHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private MemberLinkReader $links,
        private AccountNames $names,
        private PersonDirectory $people,
    ) {
    }

    public function handle(ListMemberAccessQuery $query): MemberAccessPage
    {
        $this->authorization->requirePermission('identity.manage');
        $this->authorization->requirePermission('people.manage');
        if ($query->limit < 1 || $query->limit > 100 || $query->offset < 0 || $query->offset > 100000) {
            throw IdentityException::invalid('Choose a valid page.');
        }

        $rows = $this->links->memberLinks($query->limit + 1, $query->offset);
        $visible = array_slice($rows, 0, $query->limit);
        $labels = $this->names->names(array_map(static fn (MemberLinkSummary $link): string => $link->accountId, $visible));

        return new MemberAccessPage(
            array_map(fn (MemberLinkSummary $link): MemberAccessEntry => new MemberAccessEntry(
                $link->accountId,
                $labels[$link->accountId] ?? 'Account unavailable',
                $link->personId,
                $this->people->get($link->personId)->name ?? 'Person unavailable',
                $link->active,
                $link->version,
            ), $visible),
            count($rows) > $query->limit,
        );
    }
}
