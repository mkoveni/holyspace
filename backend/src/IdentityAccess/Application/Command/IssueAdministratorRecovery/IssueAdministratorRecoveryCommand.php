<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\IssueAdministratorRecovery;

use App\Shared\Application\CQRS\Command;

final readonly class IssueAdministratorRecoveryCommand implements Command
{
    public function __construct(
        public string $username,
        public string $reference,
        public string $destination,
    ) {
    }
}
