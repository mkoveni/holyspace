<?php

declare(strict_types=1);

namespace App\People\Application\Command\AddContact;

use App\People\Domain\ValueObject\ContactKind;
use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class AddContactCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $personId,
        public int $expectedVersion,
        public ContactKind $kind,
        public string $value,
        public bool $primary,
    ) {
    }
}
