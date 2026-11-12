<?php

declare(strict_types=1);

namespace App\Events\Domain;

/**
 * The switch behind an occurrence's QR link. The link token carries the generation, so rotating it makes every
 * code printed or shared earlier stop working.
 */
final readonly class VisitLink
{
    private function __construct(public string $occurrenceId, public bool $enabled, public int $generation)
    {
        EventRules::id($occurrenceId);
        if ($generation < 1) {
            throw EventException::invalid('Invalid check-in link generation.');
        }
    }

    public static function enable(string $occurrenceId): self
    {
        return new self($occurrenceId, true, 1);
    }

    public static function reconstitute(string $occurrenceId, bool $enabled, int $generation): self
    {
        return new self($occurrenceId, $enabled, $generation);
    }

    public function enabled(): self
    {
        return new self($this->occurrenceId, true, $this->generation);
    }

    public function disabled(): self
    {
        return new self($this->occurrenceId, false, $this->generation);
    }

    public function rotated(): self
    {
        return new self($this->occurrenceId, true, $this->generation + 1);
    }

    public function accepts(int $generation): bool
    {
        return $this->enabled && $generation === $this->generation;
    }
}
