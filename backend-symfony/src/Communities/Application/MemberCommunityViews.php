<?php

declare(strict_types=1);

namespace App\Communities\Application;

use App\Communities\Application\Contract\LeaderEntry;
use App\Communities\Application\Contract\MemberCommunityDetails;
use App\Communities\Application\Contract\MemberCommunityLeader;
use App\Communities\Application\Contract\MemberCommunityReader;
use App\Communities\Application\Contract\MemberGroupMeeting;
use App\Communities\Application\Contract\MemberMeetingRow;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;
use App\Events\Application\Contract\CalendarOccurrence;
use App\Events\Application\Contract\CommunityScheduleReader;
use App\People\Application\Contract\PersonDirectory;
use DateTimeImmutable;

/** Java `MemberCommunityViews`: leader names for an own community, and meeting dates from Events. */
final readonly class MemberCommunityViews
{
    public function __construct(
        private MemberCommunityReader $reader,
        private PersonDirectory $people,
        private CommunityScheduleReader $calendar,
    ) {
    }

    public function details(string $personId, string $communityId, DateTimeImmutable $at, int $limit, int $offset): MemberCommunityDetails
    {
        CommunityRules::page($limit, $offset);
        $own = $this->reader->community($personId, $communityId, $at) ?? throw CommunityException::missing();
        $page = $this->reader->leaders($personId, $communityId, $at, $limit + 1, $offset);
        $names = [];
        foreach ($this->people->getAll(array_values(array_unique(array_map(static fn (LeaderEntry $l): string => $l->personId, $page)))) as $entry) {
            if ($entry->available) {
                $names[$entry->id] = $entry->name;
            }
        }
        $leaders = [];
        foreach (\array_slice($page, 0, $limit) as $leader) {
            if (isset($names[$leader->personId])) {
                $leaders[] = new MemberCommunityLeader($names[$leader->personId], $leader->role);
            }
        }

        return new MemberCommunityDetails($own, $leaders, \count($page) > $limit);
    }

    /**
     * @param list<MemberMeetingRow> $rows
     *
     * @return list<MemberGroupMeeting>
     */
    public function resolve(array $rows): array
    {
        if ([] === $rows) {
            return [];
        }
        $dates = [];
        foreach ($this->calendar->dates(array_values(array_unique(array_map(static fn (MemberMeetingRow $r): string => $r->occurrenceId, $rows)))) as $date) {
            $dates[(string) $date->id] = $date;
        }

        return array_map(static function (MemberMeetingRow $row) use ($dates): MemberGroupMeeting {
            $date = $dates[$row->occurrenceId] ?? throw CommunityException::conflict('A meeting schedule is unavailable. Please refresh or contact the church office.');

            return self::meeting($row, $date);
        }, $rows);
    }

    private static function meeting(MemberMeetingRow $row, CalendarOccurrence $date): MemberGroupMeeting
    {
        return new MemberGroupMeeting($row->id, $row->communityId, $row->communityName, $date->startsAt, $date->endsAt, $date->timeZone, $date->venue, 'CANCELLED' === $date->status ? 'CANCELLED' : $row->status, $row->attendance);
    }
}
