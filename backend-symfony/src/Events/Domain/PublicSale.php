<?php

declare(strict_types=1);

namespace App\Events\Domain;

use DateTimeImmutable;

/** Whether an occurrence's tickets are sold to the public, and when. */
final readonly class PublicSale
{
    public function __construct(
        public string $occurrenceId,
        public bool $enabled,
        public ?DateTimeImmutable $opensAt,
        public ?DateTimeImmutable $closesAt,
        public int $maxPerOrder,
        public int $version,
    ) {
        if ($maxPerOrder < 1 || $maxPerOrder > 50 || $version < 0 || (null !== $opensAt && null !== $closesAt && $closesAt <= $opensAt)) {
            throw EventException::invalid('Choose 1–50 tickets per order and a sales window that closes after it opens.');
        }
    }

    public static function initial(string $occurrenceId): self
    {
        return new self($occurrenceId, false, null, null, 10, 0);
    }

    public function configure(bool $enabled, ?DateTimeImmutable $opensAt, ?DateTimeImmutable $closesAt, int $maxPerOrder): self
    {
        return new self($this->occurrenceId, $enabled, $opensAt, $closesAt, $maxPerOrder, $this->version + 1);
    }

    public function isOpen(DateTimeImmutable $now): bool
    {
        return $this->enabled && (null === $this->opensAt || $this->opensAt <= $now) && (null === $this->closesAt || $now < $this->closesAt);
    }
}
