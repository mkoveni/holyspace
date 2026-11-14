<?php

declare(strict_types=1);

namespace App\Events\Domain;

/** One kind of ticket for an occurrence (General, VIP) with its own price in cents and optional cap. */
final readonly class TicketType
{
    public function __construct(
        public string $id,
        public string $occurrenceId,
        public string $name,
        public string $description,
        public int $priceMinor,
        public ?int $quantityCap,
        public int $sortOrder,
        public bool $active,
        public int $version,
    ) {
        if ('' === trim($name) || mb_strlen($name) > 80 || mb_strlen($description) > 300 || 1 === preg_match('/[\x00-\x1f\x7f<>]/', $name.$description)) {
            throw EventException::invalid('A ticket type needs a name of at most 80 characters and a description of at most 300.');
        }
        if ($priceMinor < 0 || $priceMinor > 100_000_000 || (0 < $priceMinor && $priceMinor < 500)) {
            throw EventException::invalid('A paid ticket costs ZAR 5.00 or more; a free ticket costs nothing.');
        }
        if (null !== $quantityCap && ($quantityCap < 1 || $quantityCap > 10_000)) {
            throw EventException::invalid('Choose a ticket cap of 1–10,000, or leave it unlimited.');
        }
    }
}
