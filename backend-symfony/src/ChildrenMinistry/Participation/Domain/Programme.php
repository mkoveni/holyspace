<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use DateTimeImmutable;

/** A ministry programme (for example "Sunday children") with its staffing ratio. */
final readonly class Programme
{
    public string $name;

    private function __construct(
        public string $id,
        string $name,
        public ?string $currentPolicyId,
        public int $minStaff,
        public int $childrenPerStaff,
        public int $version,
        public DateTimeImmutable $createdAt,
    ) {
        $this->name = ChildrenRules::text($name, 120);
        if ($minStaff < 1 || $childrenPerStaff < 1 || $minStaff > 100 || $childrenPerStaff > 100) {
            throw ChildrenException::invalid('Invalid staffing policy.');
        }
    }

    public static function create(string $id, string $name, int $minStaff, int $childrenPerStaff, DateTimeImmutable $now): self
    {
        return new self($id, $name, null, $minStaff, $childrenPerStaff, 0, $now);
    }

    public static function reconstitute(
        string $id,
        string $name,
        ?string $currentPolicyId,
        int $minStaff,
        int $childrenPerStaff,
        int $version,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $name, $currentPolicyId, $minStaff, $childrenPerStaff, $version, $createdAt);
    }

    /** Makes the policy current, advancing the version. Callers lock the row first. */
    public function withPolicy(string $policyId, int $expectedVersion): self
    {
        ChildrenRules::version($this->version, $expectedVersion);

        return new self($this->id, $this->name, $policyId, $this->minStaff, $this->childrenPerStaff, $this->version + 1, $this->createdAt);
    }
}
