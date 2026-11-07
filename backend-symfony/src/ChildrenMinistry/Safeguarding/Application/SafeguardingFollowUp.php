<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application;

use App\ChildrenMinistry\Safeguarding\Application\Contract\FollowUpIssue;
use App\ChildrenMinistry\Safeguarding\Application\Contract\FollowUpReader;
use App\ChildrenMinistry\Safeguarding\Application\Contract\FollowUpSummary;
use App\ChildrenMinistry\Safeguarding\Application\Contract\FollowUpView;
use App\ChildrenMinistry\Safeguarding\Application\Contract\SafeguardingFollowUps;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonDirectoryEntry;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

/**
 * What needs chasing across the active enrolments: for each child and programme, whether verified guardian authority,
 * consent for the current policy and an eligible pickup permission are missing, lapsed or lapsing. Coverage by any one
 * alternative counts, so a second guardian prevents a false alert. Care review dates are included only on request.
 */
final readonly class SafeguardingFollowUp implements SafeguardingFollowUps
{
    private const int MAX_RECORDS = 1000;

    public function __construct(
        private FollowUpReader $reader,
        private PersonDirectory $people,
        private ClockInterface $clock,
        private ChurchCalendar $calendar,
    ) {
    }

    public function list(?string $programmeId, string $search, string $kind, string $state, int $days, int $limit, int $offset, bool $includeCare): FollowUpView
    {
        ChildrenRules::page($limit, $offset);
        if ($days < 1 || $days > 90 || !\in_array($kind, ['ALL', 'GUARDIAN', 'CONSENT', 'PICKUP', 'CARE'], true) || !\in_array($state, ['ALL', 'MISSING', 'EXPIRED', 'EXPIRING', 'DUE'], true)) {
            throw ChildrenException::invalid('Choose a valid follow-up view and a 1–90 day horizon.');
        }
        $now = $this->clock->now();
        $zone = new DateTimeZone($this->calendar->timeZone());
        $on = CalendarDate::fromInstant($now, $zone);
        $through = $on->plusDays($days);
        $threshold = new DateTimeImmutable($through->plusDays(1)->iso().' 00:00:00', $zone);
        $term = ChildrenRules::optional($search, 120);
        $facts = $this->reader->load($programmeId, '' === $term ? null : $this->people->matchingIds($term), $on, $now, $includeCare);
        if (\count($facts->children) > self::MAX_RECORDS) {
            throw ChildrenException::invalid('More than 1,000 active child/programme records match. Narrow the programme or child name.');
        }

        $ids = array_values(array_unique([...array_map(static fn (FollowUpChild $c): string => $c->childId, $facts->children), ...array_map(static fn (FollowUpPickup $p): string => $p->collectorId, $facts->pickups)]));
        /** @var array<string, PersonDirectoryEntry> $names */
        $names = [];
        foreach ($this->people->getAll($ids) as $entry) {
            $names[$entry->id] = $entry;
        }
        $care = [];
        foreach ($facts->care as $record) {
            $care[$record->childId] = $record->reviewOn;
        }

        $issues = [];
        $careAdded = [];
        foreach ($facts->children as $child) {
            $identity = $names[$child->childId] ?? null;
            if (null !== $identity && (!$identity->available || (null !== $identity->birthDate && !$identity->birthDate->plusYears(18)->isAfter($on)))) {
                continue;
            }
            $name = null === $identity ? 'Unavailable person' : $identity->name;
            $this->add($issues, $child, $name, 'GUARDIAN', $child->guardianUntil, $now, $threshold, 'guardian', 'Verified guardian authority');
            $this->add($issues, $child, $name, 'CONSENT', $child->consentUntil, $now, $threshold, null === $child->policyId ? 'policy' : 'consent', 'Consent for the current programme policy');
            $pickupUntil = null;
            foreach ($facts->pickups as $pickup) {
                $collector = $names[$pickup->collectorId] ?? null;
                if ($pickup->childId === $child->childId && $pickup->programmeId === $child->programmeId && null !== $collector && $collector->available && null !== $collector->birthDate && !$collector->birthDate->plusYears(18)->isAfter($on) && (null === $pickupUntil || $pickup->until > $pickupUntil)) {
                    $pickupUntil = $pickup->until;
                }
            }
            $this->add($issues, $child, $name, 'PICKUP', $pickupUntil, $now, $threshold, 'pickup', 'Eligible pickup permission with valid guardian authority');
            $review = $care[$child->childId] ?? null;
            if ($includeCare && null !== $review && !$review->isAfter($through) && !isset($careAdded[$child->childId])) {
                $careAdded[$child->childId] = true;
                $upcoming = $review->isAfter($on);
                $issues[] = new FollowUpIssue('CARE:'.$child->childId, 'CARE', $upcoming ? 'EXPIRING' : 'DUE', $child->childId, $name, null, 'All programmes', null, $review, 'care', $upcoming ? 'A care review is coming up.' : 'The current care record is due for review.');
            }
        }

        $instantOf = static fn (FollowUpIssue $issue): int => null === $issue->expiresAt
            ? (null === $issue->reviewOn ? 0 : new DateTimeImmutable($issue->reviewOn->iso().' 00:00:00', new DateTimeZone($zone->getName()))->getTimestamp())
            : $issue->expiresAt->getTimestamp();
        $urgent = static fn (FollowUpIssue $issue): int => \in_array($issue->state, ['MISSING', 'EXPIRED', 'DUE'], true) ? 0 : 1;
        usort($issues, static fn (FollowUpIssue $a, FollowUpIssue $b): int => [$urgent($a), $instantOf($a), $a->id] <=> [$urgent($b), $instantOf($b), $b->id]);

        $count = static fn (callable $test): int => \count(array_filter($issues, $test));
        $summary = new FollowUpSummary(
            \count(array_unique(array_map(static fn (FollowUpIssue $i): string => $i->childId, $issues))),
            $count(static fn (FollowUpIssue $i): bool => 'MISSING' === $i->state),
            $count(static fn (FollowUpIssue $i): bool => 'EXPIRED' === $i->state),
            $count(static fn (FollowUpIssue $i): bool => 'EXPIRING' === $i->state),
            $count(static fn (FollowUpIssue $i): bool => 'CARE' === $i->kind && 'DUE' === $i->state),
        );
        $filtered = array_values(array_filter($issues, static fn (FollowUpIssue $i): bool => ('ALL' === $kind || $i->kind === $kind) && ('ALL' === $state || $i->state === $state)));

        return new FollowUpView(\array_slice($filtered, $offset, $limit), \count($filtered), $summary, $includeCare, $through, $this->calendar->timeZone(), $now);
    }

    /** @param list<FollowUpIssue> $issues */
    private function add(array &$issues, FollowUpChild $child, string $name, string $kind, ?DateTimeImmutable $expiry, DateTimeImmutable $now, DateTimeImmutable $threshold, string $action, string $label): void
    {
        $state = null === $expiry ? 'MISSING' : ($expiry <= $now ? 'EXPIRED' : ($expiry < $threshold ? 'EXPIRING' : ''));
        if ('' === $state) {
            return;
        }
        $message = 'policy' === $action
            ? 'Publish a programme consent policy before recording current consent.'
            : $label.('MISSING' === $state ? ' is missing or no longer usable.' : ('EXPIRED' === $state ? ' has expired.' : ' expires within the follow-up window.'));
        $issues[] = new FollowUpIssue($kind.':'.$child->childId.':'.$child->programmeId, $kind, $state, $child->childId, $name, $child->programmeId, $child->programme, $expiry, null, $action, $message);
    }
}
