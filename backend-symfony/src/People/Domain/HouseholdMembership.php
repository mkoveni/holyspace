<?php

declare(strict_types=1);

namespace App\People\Domain;

use DateTimeImmutable;

final readonly class HouseholdMembership
{
    private function __construct(
        public string $id,
        public string $householdId,
        public string $personId,
        public bool $primary,
        public DateTimeImmutable $validFrom,
        public ?DateTimeImmutable $validTo,
    ) {
        if (null !== $validTo && $validTo <= $validFrom) {
            throw PeopleException::invalid('Membership end must follow its start.');
        }
    }

    public static function start(string $id, string $householdId, string $personId, bool $primary, DateTimeImmutable $now): self
    {
        return new self($id, $householdId, $personId, $primary, $now, null);
    }

    public static function reconstitute(
        string $id,
        string $householdId,
        string $personId,
        bool $primary,
        DateTimeImmutable $validFrom,
        ?DateTimeImmutable $validTo,
    ): self {
        return new self($id, $householdId, $personId, $primary, $validFrom, $validTo);
    }

    public function currentAt(DateTimeImmutable $at): bool
    {
        return $at >= $this->validFrom && (null === $this->validTo || $at < $this->validTo);
    }

    public function end(DateTimeImmutable $now): self
    {
        if (!$this->currentAt($now) || $now <= $this->validFrom) {
            throw PeopleException::conflict('This membership cannot be ended at this time.');
        }

        return new self($this->id, $this->householdId, $this->personId, $this->primary, $this->validFrom, $now);
    }
}
