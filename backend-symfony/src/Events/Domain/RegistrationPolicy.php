<?php

declare(strict_types=1);

namespace App\Events\Domain;

final readonly class RegistrationPolicy
{
    public string $occurrenceId;

    private function __construct(
        string $occurrenceId,
        public bool $open,
        public ?int $capacity,
        public bool $waitlistEnabled,
        public int $nextPosition,
        public int $version,
    ) {
        $this->occurrenceId = EventRules::id($occurrenceId);
        if (null !== $capacity && ($capacity < 1 || $capacity > 10000)) {
            throw EventException::invalid('Choose a capacity of 1–10,000, or leave it unlimited.');
        }
    }

    /** A closed policy with unlimited capacity and the waitlist on, created with every occurrence. */
    public static function initial(string $occurrenceId): self
    {
        return new self($occurrenceId, false, null, true, 0, 0);
    }

    public static function reconstitute(string $occurrenceId, bool $open, ?int $capacity, bool $waitlistEnabled, int $nextPosition, int $version): self
    {
        return new self($occurrenceId, $open, $capacity, $waitlistEnabled, $nextPosition, $version);
    }

    public function configure(bool $open, ?int $capacity, bool $waitlist): self
    {
        return new self($this->occurrenceId, $open, $capacity, $waitlist, $this->nextPosition, $this->version + 1);
    }

    public function allocate(): self
    {
        return new self($this->occurrenceId, $this->open, $this->capacity, $this->waitlistEnabled, $this->nextPosition + 1, $this->version + 1);
    }

    public function touch(): self
    {
        return $this->configure($this->open, $this->capacity, $this->waitlistEnabled);
    }
}
