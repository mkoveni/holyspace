<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ListCommunityLeadership;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\AppointmentView;
use App\Communities\Application\Contract\CommunityReader;
use App\Communities\Application\Contract\LeadershipView;
use App\Communities\Domain\CommunityRules;
use App\IdentityAccess\Application\Contract\AccountNames;
use App\People\Application\Contract\PersonDirectory;

final readonly class ListCommunityLeadershipHandler
{
    public function __construct(
        private CommunityAccess $access,
        private CommunityReader $reader,
        private AccountNames $accounts,
        private PersonDirectory $people,
    ) {
    }

    /** @return list<AppointmentView> */
    public function handle(ListCommunityLeadershipQuery $query): array
    {
        $this->access->directoryScope();
        $this->access->require($query->communityId);
        CommunityRules::page($query->limit, $query->offset);
        $page = $this->reader->leadership($query->communityId, $query->limit, $query->offset);
        $accountIds = array_values(array_unique(array_filter(array_map(static fn (LeadershipView $l): ?string => $l->accountId, $page))));
        $accountNames = [] === $accountIds ? [] : $this->accounts->names($accountIds);
        $personNames = [];
        foreach ($this->people->getAll(array_values(array_unique(array_map(static fn (LeadershipView $l): string => $l->personId, $page)))) as $person) {
            $personNames[$person->id] = $person->name;
        }

        return array_map(static fn (LeadershipView $l): AppointmentView => new AppointmentView(
            $l,
            null === $l->accountId ? 'No workspace login' : ($accountNames[$l->accountId] ?? 'Former account'),
            $personNames[$l->personId] ?? 'Unavailable person',
        ), $page);
    }
}
