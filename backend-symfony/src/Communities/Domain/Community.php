<?php

declare(strict_types=1);

namespace App\Communities\Domain;

/** A ministry, or a group that may sit under one parent ministry. Archiving keeps its history. */
final readonly class Community
{
    public const string MINISTRY = 'MINISTRY';
    public const string GROUP = 'GROUP';

    public string $name;

    public string $description;

    public string $campus;

    private function __construct(
        public string $id,
        public string $kind,
        ?string $name,
        ?string $description,
        public ?string $parentId,
        ?string $campus,
        public bool $active,
        public int $version,
    ) {
        if (!in_array($kind, [self::MINISTRY, self::GROUP], true) || (null !== $parentId && self::GROUP !== $kind)) {
            throw CommunityException::invalid('Choose a ministry or a group with an optional parent ministry.');
        }
        $this->name = CommunityRules::text($name, 150);
        $this->description = CommunityRules::optional($description, 5000);
        $this->campus = CommunityRules::optional($campus, 150);
    }

    public static function create(string $id, string $kind, ?string $name, ?string $description, ?string $parentId, ?string $campus): self
    {
        return new self($id, $kind, $name, $description, $parentId, $campus, true, 0);
    }

    public static function reconstitute(string $id, string $kind, string $name, string $description, ?string $parentId, string $campus, bool $active, int $version): self
    {
        return new self($id, $kind, $name, $description, $parentId, $campus, $active, $version);
    }

    public function update(?string $name, ?string $description, ?string $parentId, ?string $campus, bool $active): self
    {
        return new self($this->id, $this->kind, $name, $description, $parentId, $campus, $active, $this->version + 1);
    }

    /** Scheduling meetings bumps the version so a second schedule from the same screen is stale. */
    public function touch(): self
    {
        return new self($this->id, $this->kind, $this->name, $this->description, $this->parentId, $this->campus, $this->active, $this->version + 1);
    }

    public function requireActive(): void
    {
        if (!$this->active) {
            throw CommunityException::conflict('This community is archived.');
        }
    }
}
