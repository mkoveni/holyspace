<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application\Teams;

use App\Volunteering\Application\Contract\CredentialTypeView;
use App\Volunteering\Application\Contract\VolunteerReader;
use App\Volunteering\Application\Contract\VolunteerView;
use App\Volunteering\Domain\Credential;
use App\Volunteering\Domain\CredentialType;
use App\Volunteering\Domain\Repository\VolunteerRepository;
use App\Volunteering\Domain\Volunteer;
use DateTimeImmutable;

/** Volunteer profiles (id => person id) and the credential type catalogue. */
final readonly class MemoryVolunteers implements VolunteerReader, VolunteerRepository
{
    /**
     * @param array<string, string> $people          volunteer id => person id
     * @param list<string>          $credentialTypes
     */
    public function __construct(private LockJournal $journal, private array $people, private array $credentialTypes = [])
    {
    }

    public function volunteer(string $id): ?VolunteerView
    {
        return isset($this->people[$id]) ? new VolunteerView($id, $this->people[$id], 'ACTIVE', 0) : null;
    }

    public function credentialTypes(): array
    {
        return array_map(static fn (string $id): CredentialTypeView => new CredentialTypeView($id, 'Type '.$id), $this->credentialTypes);
    }

    public function credentials(string $volunteerId): array
    {
        return [];
    }

    public function availability(string $volunteerId, int $revision): array
    {
        return [];
    }

    public function exceptions(string $volunteerId, int $revision): array
    {
        return [];
    }

    public function lock(string $id): ?Volunteer
    {
        $this->journal->entries[] = 'volunteer:'.$id;
        $at = new DateTimeImmutable('2026-01-01T00:00:00Z');

        return isset($this->people[$id]) ? Volunteer::reconstitute($id, $this->people[$id], null, 'ACTIVE', 0, 0, $at, $at) : null;
    }

    public function insert(Volunteer $volunteer): void
    {
    }

    public function save(Volunteer $volunteer): void
    {
    }

    public function addCredentialType(CredentialType $type): void
    {
    }

    public function addCredential(Credential $credential): void
    {
    }

    public function revokeCredential(string $id, DateTimeImmutable $at): void
    {
    }

    public function addAvailability(string $volunteerId, int $revision, array $weekly, array $exceptions): void
    {
    }
}
