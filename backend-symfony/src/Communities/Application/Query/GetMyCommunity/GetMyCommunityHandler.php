<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\GetMyCommunity;

use App\Communities\Application\Contract\MemberCommunityDetails;
use App\Communities\Application\MemberCommunityViews;
use App\Communities\Domain\CommunityException;
use App\IdentityAccess\Application\Contract\MemberIdentity;
use Psr\Clock\ClockInterface;

/** An own current community with its current leaders' names; anything else is 404. */
final readonly class GetMyCommunityHandler
{
    public function __construct(private MemberIdentity $identity, private MemberCommunityViews $views, private ClockInterface $clock)
    {
    }

    public function handle(GetMyCommunityQuery $query): MemberCommunityDetails
    {
        $person = $this->identity->currentPerson() ?? throw CommunityException::missing();

        return $this->views->details($person, $query->communityId, $this->clock->now(), $query->leaderLimit, $query->leaderOffset);
    }
}
