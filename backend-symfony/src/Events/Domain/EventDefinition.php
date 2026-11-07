<?php

declare(strict_types=1);

namespace App\Events\Domain;

final readonly class EventDefinition
{
    private const array TYPES = ['SERVICE', 'MEETING', 'CONFERENCE', 'OUTREACH', 'CLASS', 'OTHER'];

    public string $id;
    public string $title;
    public string $description;

    private function __construct(
        string $id,
        string $title,
        public string $type,
        string $description,
        public bool $active,
        public int $version,
    ) {
        $this->id = EventRules::id($id);
        $this->title = EventRules::text($title, 200);
        $this->description = EventRules::optional($description, 5000);
        if (!\in_array($type, self::TYPES, true)) {
            throw EventException::invalid('Choose an event type.');
        }
    }

    public static function create(string $id, string $title, string $type, string $description): self
    {
        return new self($id, $title, $type, $description, true, 0);
    }

    public static function reconstitute(string $id, string $title, string $type, string $description, bool $active, int $version): self
    {
        return new self($id, $title, $type, $description, $active, $version);
    }

    public function edit(string $title, string $type, string $description, bool $active, int $expectedVersion): self
    {
        EventRules::version($this->version, $expectedVersion);

        return new self($this->id, $title, $type, $description, $active, $this->version + 1);
    }

    /** Advances the version when a child series changes. */
    public function touch(): self
    {
        return new self($this->id, $this->title, $this->type, $this->description, $this->active, $this->version + 1);
    }
}
