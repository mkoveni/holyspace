<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation;

use App\ChildrenMinistry\Sessions\Application\PreviewOccurrence;
use App\ChildrenMinistry\Sessions\Application\PreviewTeamMember;
use App\ChildrenMinistry\Sessions\Application\ScheduledSession;
use App\ChildrenMinistry\Sessions\Application\SeriesDetail;
use App\ChildrenMinistry\Sessions\Application\SeriesPage;
use App\ChildrenMinistry\Sessions\Application\SeriesPreview;
use App\ChildrenMinistry\Sessions\Application\SeriesSummary;
use App\Shared\Presentation\Json;

/** Wire format of recurring series, matching the Java API's JSON field names. */
final class SessionSeriesJson
{
    private function __construct()
    {
    }

    /** @return array<string, mixed> */
    public static function preview(SeriesPreview $preview): array
    {
        return [
            'className' => $preview->className,
            'roomName' => $preview->roomName,
            'timeZone' => $preview->timeZone,
            'seatLimit' => $preview->seatLimit,
            'team' => array_map(static fn (PreviewTeamMember $member): array => ['workerId' => $member->workerId, 'name' => $member->name, 'role' => $member->role->value], $preview->team),
            'occurrences' => array_map(static fn (PreviewOccurrence $occurrence): array => [
                'slot' => [
                    'date' => $occurrence->slot->date->iso(),
                    'startsAt' => Json::instant($occurrence->slot->startsAt),
                    'endsAt' => Json::instant($occurrence->slot->endsAt),
                    'excluded' => $occurrence->slot->excluded,
                ],
                'conflicts' => $occurrence->conflicts,
                'staffingWarnings' => $occurrence->staffingWarnings,
            ], $preview->occurrences),
            'checkedAt' => Json::instant($preview->checkedAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function page(SeriesPage $page): array
    {
        return ['series' => array_map(self::summary(...), $page->series), 'total' => $page->total];
    }

    /** @return array<string, mixed> */
    public static function detail(SeriesDetail $detail): array
    {
        return [
            'series' => self::summary($detail->series),
            'sessions' => array_map(static fn (ScheduledSession $session): array => [
                'id' => $session->id,
                'date' => $session->date->iso(),
                'startsAt' => Json::instant($session->startsAt),
                'endsAt' => Json::instant($session->endsAt),
                'status' => $session->status->value,
            ], $detail->sessions),
        ];
    }

    /** @return array<string, mixed> */
    private static function summary(SeriesSummary $summary): array
    {
        return [
            'id' => $summary->id,
            'name' => $summary->name,
            'sourceSessionId' => $summary->sourceSessionId,
            'className' => $summary->className,
            'roomName' => $summary->roomName,
            'timeZone' => $summary->timeZone,
            'firstDate' => $summary->firstDate->iso(),
            'intervalWeeks' => $summary->intervalWeeks,
            'occurrences' => $summary->occurrences,
            'totalSessions' => $summary->totalSessions,
            'plannedSessions' => $summary->plannedSessions,
            'cancelledSessions' => $summary->cancelledSessions,
            'stopped' => $summary->stopped,
            'version' => $summary->version,
        ];
    }
}
