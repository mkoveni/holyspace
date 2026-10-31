<?php

declare(strict_types=1);

namespace App\People\Application\Command\AddFamilyMember;

use App\Shared\Application\CQRS\Command;

final readonly class AddFamilyMemberCommand implements Command
{
    public function __construct(
        public string $familyId,
        public string $personId,
        public string $role,
    ) {
    }
}
