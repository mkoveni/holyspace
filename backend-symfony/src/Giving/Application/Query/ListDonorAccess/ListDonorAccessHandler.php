<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\ListDonorAccess;

use App\Giving\Application\Contract\DonorAccessLink;
use App\Giving\Application\Contract\DonorAccessPage;
use App\Giving\Application\Contract\DonorLinkReader;
use App\Giving\Application\Contract\DonorLinkView;
use App\Giving\Application\Contract\OnlineFundDirectory;
use App\Giving\Domain\GivingException;
use App\IdentityAccess\Application\Contract\AccountNames;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;

/** Verified donor links and the online fund catalogue. Needs identity.manage and giving.manage. */
final readonly class ListDonorAccessHandler
{
    public function __construct(
        private PermissionChecker $permissions,
        private DonorLinkReader $links,
        private OnlineFundDirectory $funds,
        private PersonDirectory $people,
        private AccountNames $accounts,
    ) {
    }

    public function handle(ListDonorAccessQuery $query): DonorAccessPage
    {
        $this->permissions->requirePermission('identity.manage');
        $this->permissions->requirePermission('giving.manage');
        if ($query->limit < 1 || $query->limit > 100 || $query->offset < 0 || $query->offset > 100000) {
            throw GivingException::invalid('Choose a valid page.');
        }
        $rows = $this->links->links($query->limit + 1, $query->offset);
        $page = \array_slice($rows, 0, $query->limit);
        $accountNames = [] === $page ? [] : $this->accounts->names(array_map(static fn (DonorLinkView $link): string => $link->accountId, $page));
        $personNames = [];
        $people = array_values(array_unique(array_map(static fn (DonorLinkView $link): string => $link->personId, $page)));
        foreach ([] === $people ? [] : $this->people->getAll($people) as $entry) {
            $personNames[$entry->id] = $entry->name;
        }
        $catalogue = $this->funds->funds(true);
        if (\count($catalogue) > OnlineFundDirectory::CATALOGUE_LIMIT) {
            throw GivingException::conflict('The fund catalogue exceeds this view. Contact support.');
        }

        return new DonorAccessPage(
            array_map(static fn (DonorLinkView $link): DonorAccessLink => new DonorAccessLink(
                $link->accountId,
                $accountNames[$link->accountId] ?? 'Account unavailable',
                $link->personId,
                $personNames[$link->personId] ?? 'Person unavailable',
                $link->active,
                $link->version,
            ), $page),
            $catalogue,
            \count($rows) > $query->limit,
        );
    }
}
