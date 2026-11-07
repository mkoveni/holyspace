<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\EventJournal;
use App\Events\Domain\EventException;
use App\Events\Domain\OccurrenceCloseout;
use App\Events\Domain\Repository\CloseoutRepository;
use App\Events\Domain\Repository\OccurrenceRepository;
use Psr\Clock\ClockInterface;

/** Closing and reopening an occurrence's closeout. Only the occurrence row is locked. */
final readonly class EventCloseouts
{
    public function __construct(
        private OccurrenceRepository $occurrences,
        private CloseoutRepository $closeouts,
        private EventReadiness $readiness,
        private EventJournal $journal,
        private ClockInterface $clock,
    ) {
    }

    public function close(string $actorId, string $occurrenceId, int $expectedVersion, ?string $hash, string $note, string $followUp, bool $attendanceReviewed, bool $followUpReviewed): void
    {
        $occurrence = $this->occurrences->findForUpdate($occurrenceId) ?? throw EventException::missing('Occurrence');
        $review = $this->readiness->review($occurrenceId);
        if (!$review->notices->complete) {
            throw EventException::conflict('The notice summary is incomplete. This occurrence needs a separate review.');
        }
        if ($review->hash !== $hash) {
            throw EventException::conflict('The reviewed schedule changed. Preview the current details before applying.');
        }
        $current = $this->closeouts->find($occurrenceId) ?? OccurrenceCloseout::open($occurrenceId);
        $next = $current->close(
            $occurrence,
            $expectedVersion,
            (string) $hash,
            $note,
            $followUp,
            $attendanceReviewed,
            $followUpReviewed,
            $review->attendance->waitlisted > 0 || $review->notices->unresolved() > 0,
            $actorId,
            $this->clock->now(),
        );
        $this->persist($next, $review->attendance->attended, $review->attendance->absent, $review->attendance->waitlisted, $review->notices->unresolved());
    }

    public function reopen(string $actorId, string $occurrenceId, int $expectedVersion, string $reason): void
    {
        $this->occurrences->findForUpdate($occurrenceId) ?? throw EventException::missing('Occurrence');
        $current = $this->closeouts->find($occurrenceId) ?? OccurrenceCloseout::open($occurrenceId);
        $next = $current->reopen($expectedVersion, $reason, $actorId, $this->clock->now());
        $review = $this->readiness->review($occurrenceId);
        $this->persist($next, $review->attendance->attended, $review->attendance->absent, $review->attendance->waitlisted, $review->notices->unresolved());
    }

    private function persist(OccurrenceCloseout $next, int $attended, int $absent, int $waitlisted, int $unresolved): void
    {
        $this->closeouts->save($next, $attended, $absent, $waitlisted, $unresolved);
        $this->journal->audit((string) $next->updatedBy, 'occurrence.closeout.'.strtolower($next->status), $next->occurrenceId, $next->note, $next->updatedAt ?? $this->clock->now());
    }
}
