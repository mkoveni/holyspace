<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation;

use App\ChildrenMinistry\Sessions\Application\SessionView;
use App\ChildrenMinistry\Sessions\Application\StaffView;
use App\Shared\Presentation\Json;

/** Wire format of sessions, matching the Java API's JSON field names. */
final class SessionJson
{
    private function __construct()
    {
    }

    /** @return array<string, mixed> */
    public static function view(SessionView $view): array
    {
        $session = $view->session;

        return [
            'session' => [
                'id' => $session->id,
                'classId' => $session->classId,
                'roomId' => $session->roomId,
                'startsAt' => Json::instant($session->startsAt),
                'endsAt' => Json::instant($session->endsAt),
                'seatLimit' => $session->seatLimit,
                'status' => $session->status->value,
                'version' => $session->version,
            ],
            'className' => $view->className,
            'roomName' => $view->roomName,
            'timeZone' => $view->timeZone,
            'effectiveCapacity' => $view->effectiveCapacity,
            'occupancy' => $view->occupancy,
            'staffingNeedsReview' => $view->staffingNeedsReview,
            'staff' => array_map(static fn (StaffView $staff): array => [
                'personId' => $staff->personId,
                'name' => $staff->name,
                'role' => $staff->role->value,
                'present' => $staff->present,
                'issues' => $staff->issues,
            ], $view->staff),
        ];
    }

    /**
     * @param list<SessionView> $views
     *
     * @return list<array<string, mixed>>
     */
    public static function views(array $views): array
    {
        return array_map(self::view(...), $views);
    }
}
