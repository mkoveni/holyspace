<?php

declare(strict_types=1);

namespace App\Events\Domain;

final readonly class Venue
{
    public string $id;
    public string $name;
    public string $address;

    private function __construct(
        string $id,
        string $name,
        string $address,
        public bool $exclusive,
        public bool $active,
        public int $version,
    ) {
        $this->id = EventRules::id($id);
        $this->name = EventRules::text($name, 150);
        $this->address = EventRules::optional($address, 1000);
    }

    public static function create(string $id, string $name, string $address, bool $exclusive): self
    {
        return new self($id, $name, $address, $exclusive, true, 0);
    }

    public static function reconstitute(string $id, string $name, string $address, bool $exclusive, bool $active, int $version): self
    {
        return new self($id, $name, $address, $exclusive, $active, $version);
    }

    /** Whether a venue is exclusive is fixed at creation; occurrences snapshot it. */
    public function edit(string $name, string $address, bool $active, int $expectedVersion): self
    {
        EventRules::version($this->version, $expectedVersion);

        return new self($this->id, $name, $address, $this->exclusive, $active, $this->version + 1);
    }
}
