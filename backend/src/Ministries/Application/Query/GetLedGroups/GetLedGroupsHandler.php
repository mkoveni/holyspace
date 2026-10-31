<?php

declare(strict_types=1);

namespace App\Ministries\Application\Query\GetLedGroups;

use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\Ministries\Application\ReadModel\MinistriesReadStore;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use Symfony\Component\Clock\ClockInterface;

final readonly class GetLedGroupsHandler
{
    public function __construct(private MinistriesReadStore $records, private AccessControl $authorization, private AuthenticatedAccount $tokens, private ClockInterface $clock)
    {
    }

    public function __invoke(GetLedGroupsQuery $message): array
    {
        if (!$this->authorization->isGranted('ministries.manage') && !$this->authorization->isGranted('ministries.lead')) {
            throw new AccessDenied('Group workspace access is required.');
        }

        return ['items' => array_values(array_filter($this->records->all('ministries'), fn ($group) => $this->allowed($group)))];
    }

    private function allowed(object $group): bool
    {
        if ($this->authorization->isGranted('ministries.manage')) {
            return true;
        }
        $user = $this->tokens->user();
        if (!$group->active || !$this->authorization->isGranted('ministries.lead') || null === $user || 'active' !== $user->status()->value || null === $user->personId()) {
            return false;
        }
        foreach ($this->records->all('memberships') as $m) {
            if ($m->ministryId === $group->id && $m->personId === $user->personId() && $m->active && 'leader' === $m->role && $m->joinedOn <= $this->clock->now()->format('Y-m-d')) {
                return true;
            }
        }

        return false;
    }
}
