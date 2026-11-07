<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Communications\Application\Contract\DeliveryReader;
use App\Communications\Application\Contract\DeliverySummary;
use App\Events\Application\Contract\AttendanceReader;
use App\Events\Application\Contract\CloseoutHistoryEntry;
use App\Events\Application\Contract\CloseoutReader;
use App\Events\Application\Contract\CloseoutView;
use App\Events\Application\Contract\EventReader;
use App\Events\Application\Contract\NoticeRef;
use App\Events\Application\Contract\NoticeTotals;
use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Application\Contract\ReadinessCheck;
use App\Events\Application\Contract\ReadinessReview;
use App\Events\Application\Contract\ReadinessView;
use App\Events\Application\Contract\RegistrationReader;
use App\Events\Application\Contract\VenueReader;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\IdentityAccess\Application\Contract\AccountNames;
use App\Volunteering\Application\Contract\ActivityStaffing;
use Psr\Clock\ClockInterface;

/**
 * Live readiness for an occurrence: the checklist, the attendance and notice totals, and the review hash that a
 * closeout must present. Staffing is a current assessment, not historical evidence.
 */
final readonly class EventReadiness
{
    private const int NOTICE_LIMIT = 10000;
    private const int BATCH = 500;

    public function __construct(
        private OccurrenceReader $occurrences,
        private EventReader $events,
        private VenueReader $venues,
        private RegistrationReader $registrations,
        private AttendanceReader $attendance,
        private CloseoutReader $closeouts,
        private DeliveryReader $deliveries,
        private ActivityStaffing $staffing,
        private AccountNames $accounts,
        private ClockInterface $clock,
    ) {
    }

    public function review(string $occurrenceId): ReadinessReview
    {
        $o = $this->occurrences->find($occurrenceId) ?? throw EventException::missing('Occurrence');
        $policy = $this->registrations->policy($occurrenceId) ?? throw EventException::missing('Registration policy');
        $event = $this->events->find($o->eventId) ?? throw EventException::missing('Event');
        $now = $this->clock->now();
        $counts = $this->attendance->counts($occurrenceId, $now);
        $messages = $this->closeouts->notices($occurrenceId, self::NOTICE_LIMIT + 1);
        $complete = \count($messages) <= self::NOTICE_LIMIT;
        $considered = \array_slice($messages, 0, self::NOTICE_LIMIT);

        $tally = ['submitted' => 0, 'delivered' => 0, 'pending' => 0, 'failed' => 0, 'captured' => 0, 'cancelled' => 0];
        $revisions = [];
        foreach (array_chunk($considered, self::BATCH) as $batch) {
            $summaries = $this->deliveries->summaries(array_map(static fn (NoticeRef $n): string => $n->id, $batch));
            foreach ($batch as $message) {
                $result = $summaries[$message->id] ?? null;
                if (null === $result) {
                    ++$tally['failed'];
                    $revisions[] = $message->id.':MISSING';
                    continue;
                }
                $revisions[] = $message->id.':'.$result->version.':'.$result->status.':'.($result->captured ? 'true' : 'false');
                ++$tally[self::outcome($result)];
            }
        }
        $notices = new NoticeTotals(
            \count($messages),
            $tally['submitted'],
            $tally['delivered'],
            $tally['pending'],
            $tally['failed'],
            $tally['captured'],
            $tally['cancelled'],
            $this->closeouts->currentReminderBookings($occurrenceId),
            $complete,
        );
        $hash = Fingerprint::of(
            $o->version,
            $event->version,
            $policy->version,
            JavaText::attendanceCounts($counts),
            $this->closeouts->attendanceRevision($occurrenceId),
            JavaText::notices($notices),
            JavaText::ids($revisions),
        );

        return new ReadinessReview($o, $counts, $notices, $hash, $complete && ('CANCELLED' === $o->status || $now >= $o->endsAt));
    }

    public function view(string $occurrenceId): ReadinessView
    {
        $review = $this->review($occurrenceId);
        $occurrence = $review->occurrence;
        $policy = $this->registrations->policy($occurrenceId) ?? throw EventException::missing('Registration policy');
        $team = $this->staffing->summarize('EVENT_OCCURRENCE', $occurrenceId);
        $closeout = $this->closeouts->closeout($occurrenceId) ?? new CloseoutView($occurrenceId, 'OPEN', 0, '', '', '', null, null);
        $activeVenue = $this->venues->find($occurrence->venueId)->active ?? false;
        $attendance = $review->attendance;
        $notices = $review->notices;

        $staffingReady = $team->complete && $team->shifts > 0 && 0 === $team->gaps;
        $staffingDetail = !$team->complete
            ? 'More than 200 duties: review the team schedule directly.'
            : (0 === $team->shifts
                ? 'No duties planned. Confirm whether staffing is needed.'
                : $team->ready.' ready of '.$team->positions.' positions · '.$team->proposed.' proposed · '.$team->gaps.' gaps');

        $checks = [
            new ReadinessCheck('venue', 'Venue', $activeVenue ? 'READY' : 'REVIEW', $occurrence->venueName.($activeVenue ? ' · Venue available' : ' · Venue is archived')),
            new ReadinessCheck(
                'registration',
                'Registration and capacity',
                $policy->open ? 'READY' : 'REVIEW',
                ($policy->open ? 'Open' : 'Closed').' · '.$attendance->confirmed.' confirmed · '.(null === $policy->capacity ? 'No seat limit' : $policy->capacity.' places').' · '.$attendance->waitlisted.' waitlisted',
            ),
            new ReadinessCheck('staffing', 'Team coverage and readiness', $staffingReady ? 'READY' : 'REVIEW', $staffingDetail),
            new ReadinessCheck(
                'reminders',
                'Participant reminders',
                $notices->remindersApproved >= $attendance->confirmed ? 'READY' : 'REVIEW',
                $notices->remindersApproved.' of '.$attendance->confirmed.' confirmed bookings have a reminder approved for the current schedule. Approval is not delivery.',
            ),
            new ReadinessCheck(
                'deliveries',
                'Email follow-up',
                $notices->complete && 0 === $notices->unresolved() ? 'READY' : 'REVIEW',
                $notices->pending.' pending · '.$notices->failed.' need attention · '.$notices->captured.' captured locally'.($notices->complete ? '' : ' · More than 10,000 notices: summary incomplete'),
            ),
        ];

        return new ReadinessView($checks, $team, $review, $closeout, 'CLOSED' === $closeout->status && $closeout->reviewHash !== $review->hash, $this->clock->now());
    }

    /** @return list<CloseoutHistoryEntry> */
    public function history(string $occurrenceId, int $limit, int $offset): array
    {
        EventRules::page($limit, $offset);
        $this->occurrences->find($occurrenceId) ?? throw EventException::missing('Occurrence');
        $history = $this->closeouts->history($occurrenceId, $limit, $offset);
        $names = $this->accounts->names(array_values(array_unique(array_map(static fn (CloseoutHistoryEntry $h): string => $h->actorId, $history))));

        return array_map(static fn (CloseoutHistoryEntry $h): CloseoutHistoryEntry => new CloseoutHistoryEntry(
            $h->id,
            $h->version,
            $h->action,
            $h->note,
            $h->followUp,
            $h->attended,
            $h->absent,
            $h->waitlisted,
            $h->unresolvedNotices,
            $h->actorId,
            $names[$h->actorId] ?? 'Former coordinator',
            $h->occurredAt,
        ), $history);
    }

    /** @return 'submitted'|'delivered'|'pending'|'failed'|'captured'|'cancelled' */
    private static function outcome(DeliverySummary $result): string
    {
        if ($result->captured) {
            return 'captured';
        }

        return match ($result->status) {
            'SENT' => 'submitted',
            'DELIVERED' => 'delivered',
            'CANCELLED' => 'cancelled',
            'QUEUED', 'SCHEDULED', 'SENDING' => 'pending',
            default => 'failed',
        };
    }
}
