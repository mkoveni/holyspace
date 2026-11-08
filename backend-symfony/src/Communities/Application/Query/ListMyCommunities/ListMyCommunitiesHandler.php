<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ListMyCommunities;

use App\Communities\Application\Contract\MemberCommunityPage;
use App\Communities\Application\Contract\MemberCommunityReader;
use App\Communities\Domain\Community;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;
use App\IdentityAccess\Application\Contract\MemberIdentity;
use Psr\Clock\ClockInterface;

/** The caller's current communities; no staff permission is involved, the verified person bounds the result. */
final readonly class ListMyCommunitiesHandler
{
    public function __construct(private MemberIdentity $identity, private MemberCommunityReader $reader, private ClockInterface $clock)
    {
    }

    public function handle(ListMyCommunitiesQuery $query): MemberCommunityPage
    {
        CommunityRules::page($query->limit, $query->offset);
        if (null !== $query->kind && Community::GROUP !== $query->kind && Community::MINISTRY !== $query->kind) {
            throw CommunityException::invalid('Choose ministry or group.');
        }
        $person = $this->identity->currentPerson();
        if (null === $person) {
            return new MemberCommunityPage(false, [], false);
        }
        $rows = $this->reader->communities($person, $query->kind, $this->clock->now(), $query->limit + 1, $query->offset);

        return new MemberCommunityPage(true, \array_slice($rows, 0, $query->limit), \count($rows) > $query->limit);
    }
}
