<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Events\Application\Contract\PolicyView;
use App\Events\Application\Contract\RegistrationCounts;
use App\Events\Application\Contract\RegistrationReader;
use App\Events\Application\Contract\RegistrationView;
use App\Events\Domain\EventRegistration;
use App\Events\Domain\RegistrationPolicy;
use App\Events\Domain\Repository\RegistrationRepository;
use DateTimeImmutable;

/** In-memory test double: no database. */
final class InMemoryRegistrationRepository implements RegistrationRepository, RegistrationReader
{
    /** @var array<string, RegistrationPolicy> */
    public array $policies = [];
    /** @var array<string, RegistrationCounts> */
    public array $counts = [];
    /** @var list<string> */
    public array $cancelled = [];
    /** @var array<string, EventRegistration> */
    public array $rows = [];
    /** @var list<array{string, int}> */
    public array $promotions = [];

    public function findPolicy(string $occurrenceId): ?RegistrationPolicy
    {
        return $this->policies[$occurrenceId] ?? null;
    }

    public function insertPolicy(RegistrationPolicy $policy): void
    {
        $this->policies[$policy->occurrenceId] = $policy;
    }

    public function savePolicy(RegistrationPolicy $policy): void
    {
        $this->policies[$policy->occurrenceId] = $policy;
    }

    public function cancelOccurrenceRegistrations(string $occurrenceId, string $actorId, string $reason, DateTimeImmutable $now): void
    {
        $this->cancelled[] = $occurrenceId;
    }

    public function findRegistration(string $id): ?EventRegistration
    {
        return $this->rows[$id] ?? null;
    }

    public function findRegistrationOfPerson(string $occurrenceId, string $personId): ?EventRegistration
    {
        foreach ($this->rows as $row) {
            if ($row->occurrenceId === $occurrenceId && $row->personId === $personId) {
                return $row;
            }
        }

        return null;
    }

    public function insertRegistration(EventRegistration $registration): void
    {
        $this->rows[$registration->id] = $registration;
    }

    public function saveRegistration(EventRegistration $registration): void
    {
        $this->rows[$registration->id] = $registration;
    }

    public function promote(string $occurrenceId, int $places, string $actorId, DateTimeImmutable $now): void
    {
        $this->promotions[] = [$occurrenceId, $places];
        $queue = array_filter($this->rows, static fn (EventRegistration $r): bool => $r->occurrenceId === $occurrenceId && 'WAITLISTED' === $r->status);
        usort($queue, static fn (EventRegistration $a, EventRegistration $b): int => $a->position <=> $b->position);
        foreach (\array_slice($queue, 0, $places) as $waiting) {
            $this->rows[$waiting->id] = EventRegistration::reconstitute($waiting->id, $waiting->occurrenceId, $waiting->personId, 'CONFIRMED', $waiting->position, $waiting->revision->advance($now));
        }
    }

    public function find(string $id): ?RegistrationView
    {
        return null;
    }

    public function registrations(string $occurrenceId, ?string $status, int $limit, int $offset): array
    {
        return [];
    }

    public function registrationCount(string $occurrenceId, ?string $status): int
    {
        return 0;
    }

    public function policy(string $occurrenceId): ?PolicyView
    {
        $p = $this->policies[$occurrenceId] ?? null;

        return null === $p ? null : new PolicyView($p->occurrenceId, $p->open, $p->capacity, $p->waitlistEnabled, $p->nextPosition, $p->version);
    }

    public function counts(string $occurrenceId): RegistrationCounts
    {
        if (isset($this->counts[$occurrenceId])) {
            return $this->counts[$occurrenceId];
        }
        $tally = ['CONFIRMED' => 0, 'WAITLISTED' => 0, 'CANCELLED' => 0];
        foreach ($this->rows as $row) {
            if ($row->occurrenceId === $occurrenceId) {
                ++$tally[$row->status];
            }
        }

        return new RegistrationCounts($tally['CONFIRMED'], $tally['WAITLISTED'], $tally['CANCELLED']);
    }

    public function countsMany(array $occurrenceIds): array
    {
        return array_intersect_key($this->counts, array_flip($occurrenceIds));
    }
}
