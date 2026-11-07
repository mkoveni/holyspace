<?php

declare(strict_types=1);

namespace App\People\Application;

use App\People\Domain\ValueObject\ContactKind;
use DateTimeImmutable;

/** Read model of a contact method, current or ended. */
final readonly class ContactView
{
    public function __construct(
        public string $id,
        public string $personId,
        public ContactKind $kind,
        public string $value,
        public bool $primary,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $endedAt,
    ) {
    }
}
