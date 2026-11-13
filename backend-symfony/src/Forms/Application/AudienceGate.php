<?php

declare(strict_types=1);

namespace App\Forms\Application;

use App\Communities\Application\Contract\CommunityMembershipReader;
use App\Forms\Domain\Audience;
use App\Forms\Domain\FormException;
use App\IdentityAccess\Application\Contract\MemberIdentity;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use DateTimeImmutable;

/**
 * Who may open and answer an internal form. Members need an active member link, staff the `forms.respond` permission,
 * and group forms an active membership of one of the chosen groups. Rechecked on every fetch and every submit.
 */
final readonly class AudienceGate
{
    public function __construct(
        private MemberIdentity $identity,
        private PermissionChecker $permissions,
        private CommunityMembershipReader $communities,
    ) {
    }

    /**
     * @param list<string> $groupIds
     *
     * @return array{allowed: bool, personId: string|null}
     */
    public function check(Audience $audience, array $groupIds, string $accountId, DateTimeImmutable $now): array
    {
        $person = $this->identity->activePerson($accountId);
        $allowed = match ($audience) {
            Audience::Public => false,
            Audience::Members => null !== $person,
            Audience::Staff => $this->permissions->hasPermission('forms.respond'),
            Audience::Groups => null !== $person && [] !== array_intersect($groupIds, $this->communities->activeCommunityIds($person, $now)),
        };

        return ['allowed' => $allowed, 'personId' => $person];
    }

    /**
     * @param list<string> $groupIds
     *
     * @return string|null the caller's person id, which a staff member without a member link does not have
     */
    public function require(Audience $audience, array $groupIds, string $accountId, DateTimeImmutable $now): ?string
    {
        $result = $this->check($audience, $groupIds, $accountId, $now);
        if (!$result['allowed']) {
            throw FormException::forbidden('This form is not available to you.');
        }

        return $result['personId'];
    }
}
