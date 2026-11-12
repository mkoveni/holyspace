<?php

declare(strict_types=1);

namespace App\People\Domain;

use App\People\Domain\ValueObject\AgeCategory;
use App\People\Domain\ValueObject\MembershipType;
use App\People\Domain\ValueObject\PersonDetails;
use App\Shared\Domain\CalendarDate;
use App\Shared\Domain\Revision;
use DateTimeImmutable;

/** Aggregate root. Every change returns a new revision; the Revision owns the version and timestamps. */
final readonly class Person
{
    private function __construct(
        public string $id,
        public PersonDetails $details,
        public MembershipType $membershipType,
        public ?DateTimeImmutable $archivedAt,
        public Revision $revision,
    ) {
    }

    public static function register(
        string $id,
        PersonDetails $details,
        ?MembershipType $membership,
        CalendarDate $today,
        DateTimeImmutable $now,
    ): self {
        $details->validateAt($today);

        return new self($id, $details, $membership ?? MembershipType::Visitor, null, Revision::start($now));
    }

    public static function reconstitute(
        string $id,
        PersonDetails $details,
        MembershipType $membershipType,
        ?DateTimeImmutable $archivedAt,
        Revision $revision,
    ): self {
        return new self($id, $details, $membershipType, $archivedAt, $revision);
    }

    public function update(
        PersonDetails $details,
        MembershipType $membership,
        int $expectedVersion,
        CalendarDate $today,
        DateTimeImmutable $now,
    ): self {
        $revision = $this->revision->next($expectedVersion, $now);
        $details->validateAt($today);

        return new self($this->id, $details, $membership, $this->archivedAt, $revision);
    }

    /** A visitor the church has welcomed becomes an active member. Anyone who already belongs, or is archived, is left alone. */
    public function admitAsMember(DateTimeImmutable $now): self
    {
        $this->requireAvailable();
        if (!\in_array($this->membershipType, [MembershipType::Visitor, MembershipType::RegularVisitor], true)) {
            throw PeopleException::conflict('Only a visitor can be made a member.');
        }

        return new self($this->id, $this->details, MembershipType::ActiveMember, $this->archivedAt, $this->revision->advance($now));
    }

    public function archive(bool $archived, int $expectedVersion, DateTimeImmutable $now): self
    {
        $revision = $this->revision->next($expectedVersion, $now);

        return new self($this->id, $this->details, $this->membershipType, $archived ? ($this->archivedAt ?? $now) : null, $revision);
    }

    /** Records a change to a child collection (contacts, addresses) so concurrent editors notice it. */
    public function touch(int $expectedVersion, DateTimeImmutable $now): self
    {
        return new self($this->id, $this->details, $this->membershipType, $this->archivedAt, $this->revision->next($expectedVersion, $now));
    }

    public function requireAvailable(): void
    {
        if (null !== $this->archivedAt) {
            throw PeopleException::conflict('Restore the archived person before changing associations.');
        }
    }

    public function ageCategory(CalendarDate $asOf): AgeCategory
    {
        return AgePolicy::category($this->details->dateOfBirth, $asOf);
    }
}
