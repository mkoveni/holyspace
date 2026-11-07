<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\CreateRole;

use App\IdentityAccess\Application\Authorization\Authorization;
use App\IdentityAccess\Application\Contract\AccessWriter;
use App\IdentityAccess\Application\Contract\IdentityAuditWriter;
use App\IdentityAccess\Domain\IdentityException;
use App\IdentityAccess\Domain\IdentityRules;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class CreateRoleHandler implements CommandHandler
{
    public function __construct(
        private Authorization $authorization,
        private AccessWriter $writer,
        private IdentityAuditWriter $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
    ) {
    }

    /** @return string the new role id */
    public function handle(CreateRoleCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'identity.manage');
        $name = IdentityRules::normalize($command->name);
        if (1 !== preg_match('/\A[a-z0-9][a-z0-9._-]{1,79}\z/', $name)) {
            throw IdentityException::invalid('Invalid name.');
        }
        $description = trim($command->description ?? '');
        if (mb_strlen($description) > 255) {
            throw IdentityException::invalid('Description must be at most 255 characters.');
        }

        $id = $this->ids->uuid();
        $this->writer->createRole($id, $name, $description);
        $this->audit->audit($command->actorId, 'role.created', $id, $this->clock->now());

        return $id;
    }
}
