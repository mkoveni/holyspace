<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\VerifyMemberLink;

use App\IdentityAccess\Application\Contract\AccountDirectory;
use App\IdentityAccess\Application\Contract\AccountIdentityGuard;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\IdentityAccess\Application\Contract\PersonBindingPolicy;
use App\IdentityAccess\Domain\IdentityException;
use App\IdentityAccess\Domain\MemberLink;
use App\IdentityAccess\Domain\Repository\MemberLinkRepository;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Staff verification that an account belongs to a person. Needs identity.manage and people.manage. */
final readonly class VerifyMemberLinkHandler implements CommandHandler
{
    /** @param iterable<PersonBindingPolicy> $policies vetoes from contexts holding verified identity of their own */
    public function __construct(
        private PermissionChecker $authorization,
        private AccountIdentityGuard $guard,
        private AccountDirectory $accounts,
        private MemberLinkRepository $links,
        private PersonDirectory $people,
        private iterable $policies,
        private ClockInterface $clock,
    ) {
    }

    public function handle(VerifyMemberLinkCommand $command): void
    {
        $this->authorize($command);
        $evidence = trim($command->evidence);
        if ('' === $evidence || mb_strlen($evidence) > 1000) {
            throw IdentityException::invalid('Record verification evidence or a revocation reason (at most 1000 characters).');
        }

        // Lock order: account root, then the link, then the person. Permissions are re-checked once the locks are held.
        $this->guard->lock($command->accountId);
        $prior = $this->links->lock($command->accountId);
        $this->authorize($command);

        if ($command->active && !$this->mayLink($command)) {
            throw IdentityException::conflict('Choose an active account and available person matching existing verified financial identity.');
        }
        if (null === $prior && (0 !== $command->expectedVersion || !$command->active)) {
            throw IdentityException::conflict('Refresh member access before trying again.');
        }

        $now = $this->clock->now();
        $link = null === $prior
            ? MemberLink::verify($command->accountId, $command->personId, $command->actorId, $now)
            : $prior->revise($command->personId, $command->active, $command->expectedVersion, $command->actorId, $now);
        $this->links->save($link, $evidence);
    }

    private function authorize(VerifyMemberLinkCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'identity.manage');
        $this->authorization->requireActorPermission($command->actorId, 'people.manage');
    }

    private function mayLink(VerifyMemberLinkCommand $command): bool
    {
        if (!$this->accounts->active($command->accountId)) {
            return false;
        }
        $person = $this->people->lock($command->personId);
        if (null === $person || !$person->available) {
            return false;
        }
        foreach ($this->policies as $policy) {
            if (!$policy->permits($command->accountId, $command->personId)) {
                return false;
            }
        }

        return true;
    }
}
