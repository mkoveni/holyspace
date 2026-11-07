<?php

declare(strict_types=1);

namespace App\People\Application;

use App\People\Domain\ValueObject\PersonDetails;

/** What a member sees of their own record: no archive state, ages or addresses, and only contacts still in use. */
final readonly class MyProfile
{
    /** @param list<ContactView> $contacts */
    public function __construct(
        public string $id,
        public PersonDetails $details,
        public string $membershipType,
        public int $version,
        public array $contacts,
    ) {
    }
}
