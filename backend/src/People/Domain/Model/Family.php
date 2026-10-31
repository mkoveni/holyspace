<?php

declare(strict_types=1);

namespace App\People\Domain\Model;

use App\People\Domain\Enum\FamilyRole;
use App\People\Domain\ValueObject\FamilyId;
use App\People\Domain\ValueObject\PersonId;
use App\Shared\Domain\ValueObject\AuditTimestamps;
use DateTimeImmutable;
use InvalidArgumentException;

final class Family
{
    private array $members = [];
    private ?PersonId $primaryContact = null;

    private function __construct(private readonly FamilyId $id, private string $name, private readonly AuditTimestamps $audit)
    {
    }

    public static function create(FamilyId $id, string $name): self
    {
        $name = trim($name);
        if ('' === $name) {
            throw new InvalidArgumentException('Family name cannot be empty.');
        }

        return new self($id, $name, AuditTimestamps::now());
    }

    public static function reconstitute(FamilyId $id, string $name, AuditTimestamps $audit, array $members = [], ?PersonId $primaryContact = null): self
    {
        $family = new self($id, trim($name), $audit);
        foreach ($members as $member) {
            $family->members[$member->personId()->toString()] = $member;
        }
        $family->primaryContact = $primaryContact;

        return $family;
    }

    public function id(): FamilyId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->audit->createdAt();
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->audit->updatedAt();
    }

    public function primaryContact(): ?PersonId
    {
        return $this->primaryContact;
    }

    public function rename(string $name): void
    {
        $name = trim($name);
        if ('' === $name) {
            throw new InvalidArgumentException('Family name cannot be empty.');
        }
        if ($name !== $this->name) {
            $this->name = $name;
            $this->audit->touch();
        }
    }

    public function addMember(PersonId $personId, FamilyRole $role): void
    {
        $this->members[$personId->toString()] = FamilyMember::create($personId, $role);
        $this->audit->touch();
    }

    public function removeMember(PersonId $personId): void
    {
        if (isset($this->members[$personId->toString()])) {
            unset($this->members[$personId->toString()]);
            if ($this->primaryContact?->toString() === $personId->toString()) {
                $this->primaryContact = null;
            }
            $this->audit->touch();
        }
    }

    public function setPrimaryContact(?PersonId $personId): void
    {
        if (null !== $personId && !isset($this->members[$personId->toString()])) {
            throw new InvalidArgumentException('Primary contact must be a family member.');
        }
        $this->primaryContact = $personId;
        $this->audit->touch();
    }

    /** @return list<FamilyMember> */
    public function members(): array
    {
        return array_values($this->members);
    }
}
