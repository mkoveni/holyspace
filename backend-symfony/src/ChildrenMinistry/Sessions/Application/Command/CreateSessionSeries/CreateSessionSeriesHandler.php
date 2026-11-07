<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\CreateSessionSeries;

use App\ChildrenMinistry\Sessions\Application\SessionSeriesPlanning;
use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionRepository;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionSeriesRepository;
use App\ChildrenMinistry\Sessions\Domain\SessionSeries;
use App\ChildrenMinistry\Sessions\Domain\SessionStaff;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * Creates every included session of a series atomically. Lock order: scheduling guard, then the source session.
 * Clashes are re-checked under the guard, so concurrent creators cannot both succeed.
 */
final readonly class CreateSessionSeriesHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private SessionSeriesRepository $series,
        private SessionRepository $sessions,
        private SessionSeriesPlanning $planning,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
    ) {
    }

    /** @return string the series id */
    public function handle(CreateSessionSeriesCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.manage');
        $draft = $command->draft;

        $this->series->lockScheduling();
        $fingerprint = $draft->fingerprint();
        $previous = $this->series->request($command->actorId, $command->requestId);
        if (null !== $previous) {
            if ($previous->requestFingerprint !== $fingerprint) {
                throw ChildrenException::conflict('This request ID was already used for a different schedule.');
            }

            return $previous->id;
        }

        $source = $this->sessions->ministrySessionForUpdate($draft->sourceSessionId) ?? throw ChildrenException::missing('Source session');
        $preview = $this->planning->preview($draft);
        if ($preview->hasConflicts()) {
            throw ChildrenException::conflict('Some dates are no longer available. Refresh the preview and skip clashes before creating the series.');
        }

        $now = $this->clock->now();
        $series = SessionSeries::create($this->ids->uuid(), $draft->name, $source, $preview->timeZone, $draft->recurrence, $now, $command->actorId, $command->requestId, $fingerprint);
        $this->series->insert($series);
        foreach ($preview->occurrences as $occurrence) {
            if ($occurrence->slot->excluded) {
                continue;
            }
            $session = MinistrySession::plan($this->ids->uuid(), $source->classId, $source->roomId, $occurrence->slot->startsAt, $occurrence->slot->endsAt, $source->seatLimit);
            $this->sessions->insertMinistrySession($session);
            $this->series->link($series->id, $session->id, $occurrence->slot->date);
            foreach ($preview->team as $member) {
                $this->sessions->saveSessionStaff(new SessionStaff($session->id, $member->workerId, $member->role, false));
            }
            $this->audit->record($command->actorId, 'session.created', $session->id, 'series:'.$series->id, $now);
        }
        $this->audit->record($command->actorId, 'session.series.created', $series->id, $series->name, $now);

        return $series->id;
    }
}
