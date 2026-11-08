<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\RevokeServingApproval;

use App\Shared\Application\Command;

/**
 * Revokes a role approval (`roleApproval` true) or a team membership (false) of the volunteer on the team.
 *
 * @implements Command<null>
 */
final readonly class RevokeServingApprovalCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $teamId,
        public int $expectedVersion,
        public string $volunteerId,
        public string $recordId,
        public bool $roleApproval,
    ) {
    }
}
