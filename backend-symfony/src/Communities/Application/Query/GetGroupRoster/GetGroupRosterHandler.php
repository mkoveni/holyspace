<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\GetGroupRoster;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\MeetingReader;
use App\Communities\Application\Contract\RosterPage;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;

/** One meeting's roster page with its counts, for managers and the group's current leaders. */
final readonly class GetGroupRosterHandler
{
    public function __construct(private CommunityAccess $access, private MeetingReader $reader)
    {
    }

    public function handle(GetGroupRosterQuery $query): RosterPage
    {
        $this->access->directoryScope();
        $meeting = $this->reader->meeting($query->meetingId) ?? throw CommunityException::missing();
        $this->access->require($meeting->communityId);
        CommunityRules::page($query->limit, $query->offset);

        return new RosterPage(
            $this->reader->roster($meeting->id, $query->limit, $query->offset),
            $this->reader->rosterCount($meeting->id),
            $this->reader->reports([$meeting->id])[$meeting->id] ?? throw CommunityException::missing(),
        );
    }
}
