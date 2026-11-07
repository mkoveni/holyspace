<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\CreatePermission;

use App\IdentityAccess\Application\Authorization\Authorization;
use App\IdentityAccess\Application\Contract\AccessWriter;
use App\IdentityAccess\Application\Contract\IdentityAuditWriter;
use App\IdentityAccess\Domain\IdentityException;
use App\IdentityAccess\Domain\IdentityRules;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class CreatePermissionHandler implements CommandHandler
{
    public function __construct(
        private Authorization $authorization,
        private AccessWriter $writer,
        private IdentityAuditWriter $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
    ) {
    }

    /** @return string the new permission id */
    public function handle(CreatePermissionCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'identity.manage');
        $code = IdentityRules::normalize($command->code);
        if (1 !== preg_match('/\A[a-z][a-z0-9_]*[.][a-z][a-z0-9_]*\z/', $code) || strlen($code) > 100) {
            throw IdentityException::invalid('Invalid code.');
        }
        $description = trim($command->description ?? '');
        if (mb_strlen($description) > 255) {
            throw IdentityException::invalid('Description must be at most 255 characters.');
        }

        $id = $this->ids->uuid();
        $this->writer->createPermission($id, $code, $description);
        $this->audit->audit($command->actorId, 'permission.created', $id, $this->clock->now());

        return $id;
    }
}
