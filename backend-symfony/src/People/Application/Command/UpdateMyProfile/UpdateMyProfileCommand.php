<?php

declare(strict_types=1);

namespace App\People\Application\Command\UpdateMyProfile;

use App\People\Domain\ValueObject\Gender;
use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class UpdateMyProfileCommand implements Command
{
    public function __construct(
        public string $actorId,
        public ?string $titleCode,
        public string $firstName,
        public string $lastName,
        public ?Gender $gender,
        public int $expectedVersion,
    ) {
    }
}
