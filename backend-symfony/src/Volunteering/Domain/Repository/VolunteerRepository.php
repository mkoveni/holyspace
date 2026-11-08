<?php

declare(strict_types=1);

namespace App\Volunteering\Domain\Repository;

use App\Volunteering\Domain\AvailabilityException;
use App\Volunteering\Domain\AvailabilityRule;
use App\Volunteering\Domain\Credential;
use App\Volunteering\Domain\CredentialType;
use App\Volunteering\Domain\Volunteer;
use DateTimeImmutable;

interface VolunteerRepository
{
    /** Holds the volunteer row until the transaction ends. */
    public function lock(string $id): ?Volunteer;

    /** A second profile for the person or the account is a clash. */
    public function insert(Volunteer $volunteer): void;

    /** Saves with the previous version in the WHERE clause; an account already linked elsewhere is a clash. */
    public function save(Volunteer $volunteer): void;

    /** A duplicate code is a clash. */
    public function addCredentialType(CredentialType $type): void;

    public function addCredential(Credential $credential): void;

    /** Sets `revoked_at` only while it is null. */
    public function revokeCredential(string $id, DateTimeImmutable $at): void;

    /**
     * Stores a new availability revision; earlier revisions stay as history.
     *
     * @param list<AvailabilityRule>      $weekly
     * @param list<AvailabilityException> $exceptions
     */
    public function addAvailability(string $volunteerId, int $revision, array $weekly, array $exceptions): void;
}
