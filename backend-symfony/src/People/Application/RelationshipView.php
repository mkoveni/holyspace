<?php

declare(strict_types=1);

namespace App\People\Application;

use DateTimeImmutable;

/** A relationship as seen from one person: the type is inverted when the person is the target. */
final readonly class RelationshipView
{
    public function __construct(
        public string $id,
        public string $relatedPersonId,
        public string $type,
        public DateTimeImmutable $validFrom,
        public ?DateTimeImmutable $validTo,
        public int $version,
    ) {
    }
}
