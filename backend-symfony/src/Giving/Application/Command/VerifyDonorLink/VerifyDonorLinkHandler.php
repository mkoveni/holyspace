<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\VerifyDonorLink;

use App\Giving\Domain\DonorLink;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\GivingRules;
use App\Giving\Domain\Repository\DonorLinkRepository;
use App\IdentityAccess\Application\Contract\AccountDirectory;
use App\IdentityAccess\Application\Contract\AccountIdentityGuard;
use App\IdentityAccess\Application\Contract\MemberIdentity;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Staff verification, revocation or restoration of an account's financial identity, with recorded evidence. Needs
 * identity.manage and giving.manage. Lock order: account root, then the donor link, then the person.
 */
final readonly class VerifyDonorLinkHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $permissions,
        private AccountIdentityGuard $guard,
        private MemberIdentity $members,
        private AccountDirectory $accounts,
        private PersonDirectory $people,
        private DonorLinkRepository $links,
        private ClockInterface $clock,
    ) {
    }

    public function handle(VerifyDonorLinkCommand $command): void
    {
        $this->authorize($command->actorId);
        $evidence = GivingRules::text($command->evidence, 1000, 'Identity verification evidence or revocation reason');

        $this->guard->lock($command->accountId);
        $member = $this->members->boundPerson($command->accountId);
        if (null !== $member && $member !== $command->personId) {
            throw GivingException::conflict('The donor must match the verified member identity.');
        }
        $prior = $this->links->lock($command->accountId);
        if ($command->active && !$this->mayLink($command)) {
            throw GivingException::invalid('Choose an active account and available person.');
        }
        if (null === $prior && (0 !== $command->expectedVersion || !$command->active)) {
            throw GivingException::conflict('Refresh donor access before trying again.');
        }
        $now = $this->clock->now();
        $link = null === $prior
            ? DonorLink::verify($command->accountId, $command->personId, $command->actorId, $now)
            : $prior->revise($command->personId, $command->active, $command->expectedVersion, $command->actorId, $now);
        $this->links->save($link, $evidence);
    }

    /** Locks the person only when the account is active, as Java's short-circuit does. */
    private function mayLink(VerifyDonorLinkCommand $command): bool
    {
        if (!$this->accounts->active($command->accountId)) {
            return false;
        }
        $person = $this->people->lock($command->personId);

        return null !== $person && $person->available;
    }

    private function authorize(string $actorId): void
    {
        $this->permissions->requireActorPermission($actorId, 'identity.manage');
        $this->permissions->requireActorPermission($actorId, 'giving.manage');
    }
}
