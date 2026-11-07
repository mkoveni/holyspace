<?php

declare(strict_types=1);

namespace App\People\Application\Command\SaveMyContact;

use App\People\Domain\ValueObject\ContactKind;
use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class SaveMyContactCommand implements Command
{
    public function __construct(
        public string $actorId,
        public ?string $contactId,
        public ?ContactKind $kind,
        public string $value,
        public int $expectedVersion,
    ) {
    }
}
