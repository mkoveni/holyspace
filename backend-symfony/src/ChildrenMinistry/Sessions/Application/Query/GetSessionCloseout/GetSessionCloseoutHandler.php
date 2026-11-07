<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetSessionCloseout;

use App\ChildrenMinistry\Sessions\Application\Contract\SessionCloseoutReader;
use App\ChildrenMinistry\Sessions\Application\Contract\SessionIncidents;
use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Sessions\Application\SessionCloseout;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\ChurchCalendar;
use Psr\Clock\ClockInterface;

/** The checklist before closing a session. Incident counts are shown only to those who manage incidents. */
final readonly class GetSessionCloseoutHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private SessionReader $sessions,
        private SessionCloseoutReader $reader,
        private ClockInterface $clock,
        private SessionIncidents $incidents,
        private ChurchCalendar $calendar,
    ) {
    }

    public function handle(GetSessionCloseoutQuery $query): SessionCloseout
    {
        $this->authorization->requireActorPermission($query->actorId, 'children.manage');
        $this->sessions->ministrySession($query->sessionId) ?? throw ChildrenException::missing('Session');
        $summary = $this->reader->summarize($query->sessionId, $this->clock->now());

        return $this->authorization->hasPermission('children.incidents.manage')
            ? $summary->withIncidents($this->incidents->counts($query->sessionId, $this->calendar->today()))
            : $summary;
    }
}
