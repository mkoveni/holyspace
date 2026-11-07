<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Command\RevokeGuardianEmail;

use App\ChildrenMinistry\Messaging\Application\Contract\VerifiedEmailReader;
use App\ChildrenMinistry\Messaging\Domain\Repository\VerifiedEmailRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Revoking invalidates any approval built on the verification; the dispatcher re-checks it before every send. */
final readonly class RevokeGuardianEmailHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private VerifiedEmailReader $reader,
        private VerifiedEmailRepository $repository,
        private PersonDirectory $people,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(RevokeGuardianEmailCommand $command): null
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.safeguarding');
        ChildrenRules::text($command->reason, 500);

        $email = $this->reader->verification($command->verificationId) ?? throw ChildrenException::missing('Verified email');
        $this->people->lock($email->guardianId) ?? throw ChildrenException::missing('Guardian');
        $now = $this->clock->now();
        $this->repository->revoke($command->verificationId, $now);
        $this->audit->record($command->actorId, 'guardian.email.revoked', $command->verificationId, '', $now);

        return null;
    }
}
