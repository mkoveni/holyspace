<?php

declare(strict_types=1);

namespace App\People\Domain;

use App\Shared\Domain\Revision;
use DateTimeImmutable;

final readonly class Household
{
    public string $name;

    private function __construct(
        public string $id,
        string $name,
        public ?string $primaryContactMembershipId,
        public Revision $revision,
    ) {
        $this->name = PeopleRules::text($name, 'Household name', 160);
    }

    public static function create(string $id, string $name, DateTimeImmutable $now): self
    {
        return new self($id, $name, null, Revision::start($now));
    }

    public static function reconstitute(string $id, string $name, ?string $primaryContactMembershipId, Revision $revision): self
    {
        return new self($id, $name, $primaryContactMembershipId, $revision);
    }

    public function update(string $name, int $expectedVersion, DateTimeImmutable $now): self
    {
        return new self($this->id, $name, $this->primaryContactMembershipId, $this->revision->next($expectedVersion, $now));
    }

    /** Sets (or clears, with null) the primary contact. The member must currently belong to this household. */
    public function contact(?HouseholdMembership $member, int $expectedVersion, DateTimeImmutable $now): self
    {
        $revision = $this->revision->next($expectedVersion, $now);
        if (null !== $member && ($member->householdId !== $this->id || !$member->currentAt($now))) {
            throw PeopleException::invalid('Primary contact must be a current member of this household.');
        }

        return new self($this->id, $this->name, $member?->id, $revision);
    }

    public function touch(int $expectedVersion, DateTimeImmutable $now): self
    {
        return $this->update($this->name, $expectedVersion, $now);
    }
}
