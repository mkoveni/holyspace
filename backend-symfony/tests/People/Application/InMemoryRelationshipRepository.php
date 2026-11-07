<?php

declare(strict_types=1);

namespace App\Tests\People\Application;

use App\People\Domain\PersonRelationship;
use App\People\Domain\Repository\RelationshipRepository;
use DateTimeImmutable;

/** In-memory test double: no database, no identity context. */
final class InMemoryRelationshipRepository implements RelationshipRepository
{
    /** @var list<PersonRelationship> */
    public array $relationships = [];
    public int $locks = 0;

    public function lockGraph(): void
    {
        ++$this->locks;
    }

    public function find(string $id): ?PersonRelationship
    {
        foreach ($this->relationships as $relationship) {
            if ($relationship->id === $id) {
                return $relationship;
            }
        }

        return null;
    }

    public function currentParentage(DateTimeImmutable $at): array
    {
        return array_values(array_filter($this->relationships, static fn (PersonRelationship $r): bool => $r->currentAt($at)));
    }

    public function insert(PersonRelationship $relationship): void
    {
        $this->relationships[] = $relationship;
    }

    public function update(PersonRelationship $relationship): void
    {
        $this->relationships = array_map(static fn (PersonRelationship $r): PersonRelationship => $r->id === $relationship->id ? $relationship : $r, $this->relationships);
    }
}
