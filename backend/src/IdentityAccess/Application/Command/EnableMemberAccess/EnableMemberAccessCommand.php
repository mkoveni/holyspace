<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\EnableMemberAccess;

use App\Shared\Application\CQRS\Command;

final readonly class EnableMemberAccessCommand implements Command
{
    public function __construct(
        public string $id,
        public string $personId,
        public ?string $expectedPersonId,
    ) {
    }
}
