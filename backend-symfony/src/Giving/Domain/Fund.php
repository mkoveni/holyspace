<?php

declare(strict_types=1);

namespace App\Giving\Domain;

/** A designated purpose gifts are allocated to. Archiving (inactive) blocks new gifts but keeps past allocations. */
final readonly class Fund
{
    public string $name;
    public string $description;

    private function __construct(
        public string $id,
        ?string $name,
        ?string $description,
        public bool $active,
        public int $version,
    ) {
        $this->name = GivingRules::text($name, 100, 'Fund name');
        $this->description = null === $description ? '' : JavaString::trim($description);
        if ('' === $id || JavaString::length($this->description) > 1000 || $version < 0) {
            throw GivingException::invalid('Invalid fund details.');
        }
    }

    /** The request id becomes the fund id, so a retried create finds the row it wrote. */
    public static function create(string $id, ?string $name, ?string $description): self
    {
        return new self($id, $name, $description, true, 0);
    }

    public static function reconstitute(string $id, string $name, string $description, bool $active, int $version): self
    {
        return new self($id, $name, $description, $active, $version);
    }

    public function revise(int $expectedVersion, ?string $name, ?string $description, bool $active): self
    {
        GivingRules::version($this->version, $expectedVersion);

        return new self($this->id, $name, $description, $active, $this->version + 1);
    }
}
