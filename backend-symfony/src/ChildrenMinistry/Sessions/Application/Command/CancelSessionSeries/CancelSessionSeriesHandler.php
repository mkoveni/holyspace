<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\CancelSessionSeries;

use App\ChildrenMinistry\Sessions\Application\Contract\AttendanceReader;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionRepository;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionSeriesRepository;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Stops a series and cancels its future planned sessions only; open, reviewed, closed and past sessions keep their
 * history. Lock order: scheduling guard, the series, then its sessions in UUID order.
 */
final readonly class CancelSessionSeriesHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private SessionSeriesRepository $series,
        private SessionRepository $sessions,
        private AttendanceReader $attendance,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(CancelSessionSeriesCommand $command): null
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.manage');

        $this->series->lockScheduling();
        $value = $this->series->forUpdate($command->seriesId) ?? throw ChildrenException::missing('Series');
        $stopped = $value->stop($command->expectedVersion);
        $reason = ChildrenRules::text($command->reason, 500);

        $ids = $this->series->sessionIds($value->id);
        sort($ids, \SORT_STRING);
        $locked = [];
        foreach ($ids as $id) {
            $locked[] = $this->sessions->ministrySessionForUpdate($id) ?? throw ChildrenException::missing('Session');
        }

        $now = $this->clock->now();
        foreach ($locked as $session) {
            if (SessionStatus::Planned !== $session->status || $session->startsAt <= $now) {
                continue;
            }
            if ($this->attendance->occupancy($session->id) > 0) {
                throw ChildrenException::conflict('Collect all children before cancelling.');
            }
            $this->sessions->updateMinistrySession($session->withStatus(SessionStatus::Cancelled, $session->version));
            $this->audit->record($command->actorId, 'session.status.changed', $session->id, $reason, $now);
        }
        $this->series->update($stopped);
        $this->audit->record($command->actorId, 'session.series.cancelled', $value->id, $reason, $now);

        return null;
    }
}
