<?php

declare(strict_types=1);

namespace App\Volunteering\Domain;

/** A serving team. Its version moves with every change to the team, its roles, memberships and approvals. */
final readonly class Team
{
    public string $name;

    private function __construct(public string $id, ?string $name, public bool $active, public int $version)
    {
        $this->name = ServingRules::text($name, 150);
    }

    public static function create(string $id, ?string $name): self
    {
        return new self($id, $name, true, 0);
    }

    public static function reconstitute(string $id, string $name, bool $active, int $version): self
    {
        return new self($id, $name, $active, $version);
    }

    public function change(?string $name, bool $active): self
    {
        return new self($this->id, $name, $active, $this->version + 1);
    }

    /** A role, membership or approval change makes an open team screen stale. */
    public function touch(): self
    {
        return new self($this->id, $this->name, $this->active, $this->version + 1);
    }

    public function requireActive(): void
    {
        if (!$this->active) {
            throw VolunteerException::conflict('The team is inactive.');
        }
    }
}
