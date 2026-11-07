<?php

declare(strict_types=1);

namespace App\People\Domain;

use App\People\Domain\ValueObject\RelationshipType;
use DateTimeImmutable;

final readonly class PersonRelationship
{
    public string $sourceId;
    public string $targetId;

    private function __construct(
        public string $id,
        string $sourceId,
        string $targetId,
        public RelationshipType $type,
        public DateTimeImmutable $validFrom,
        public ?DateTimeImmutable $validTo,
        public int $version,
    ) {
        if ($sourceId === $targetId) {
            throw PeopleException::invalid('A person cannot be related to themselves.');
        }
        if ($type->isSymmetric() && strcmp($sourceId, $targetId) > 0) {
            [$sourceId, $targetId] = [$targetId, $sourceId];
        }
        if (null !== $validTo && $validTo <= $validFrom) {
            throw PeopleException::invalid('Relationship end must follow its start.');
        }
        $this->sourceId = $sourceId;
        $this->targetId = $targetId;
    }

    public static function create(string $id, string $sourceId, string $targetId, RelationshipType $type, DateTimeImmutable $now): self
    {
        return new self($id, $sourceId, $targetId, $type, $now, null, 0);
    }

    public static function reconstitute(
        string $id,
        string $sourceId,
        string $targetId,
        RelationshipType $type,
        DateTimeImmutable $validFrom,
        ?DateTimeImmutable $validTo,
        int $version,
    ): self {
        return new self($id, $sourceId, $targetId, $type, $validFrom, $validTo, $version);
    }

    public function currentAt(DateTimeImmutable $at): bool
    {
        return $at >= $this->validFrom && (null === $this->validTo || $at < $this->validTo);
    }

    public function end(int $expectedVersion, DateTimeImmutable $now): self
    {
        PeopleRules::version($this->version, $expectedVersion);
        if (!$this->currentAt($now) || $now <= $this->validFrom) {
            throw PeopleException::conflict('This relationship cannot be ended at this time.');
        }

        return new self($this->id, $this->sourceId, $this->targetId, $this->type, $this->validFrom, $now, $this->version + 1);
    }
}
