<?php

declare(strict_types=1);

namespace App\Events\Domain\Repository;

use App\Events\Domain\EventRegistration;
use App\Events\Domain\RegistrationPolicy;
use DateTimeImmutable;

interface RegistrationRepository
{
    public function findPolicy(string $occurrenceId): ?RegistrationPolicy;

    public function insertPolicy(RegistrationPolicy $policy): void;

    /** Saves the next version. Throws a conflict when the stored version is not the previous one. */
    public function savePolicy(RegistrationPolicy $policy): void;

    public function findRegistration(string $id): ?EventRegistration;

    public function findRegistrationOfPerson(string $occurrenceId, string $personId): ?EventRegistration;

    public function insertRegistration(EventRegistration $registration): void;

    /** Saves the next version. Throws a conflict when the stored version is not the previous one. */
    public function saveRegistration(EventRegistration $registration): void;

    /** Cancels every active registration of the occurrence in one statement, writing a history row for each. */
    public function cancelOccurrenceRegistrations(string $occurrenceId, string $actorId, string $reason, DateTimeImmutable $now): void;

    /** Confirms up to $places waitlisted registrations in queue order, writing a history row for each. */
    public function promote(string $occurrenceId, int $places, string $actorId, DateTimeImmutable $now): void;
}
