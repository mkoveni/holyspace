<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use App\IdentityAccess\Application\ReadModel\IdentityAccessReadRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ContextPermissionVoter extends Voter
{
    public function __construct(private readonly IdentityAccessReadRepository $permissions)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, ['payments.manage', 'reports.read', 'reports.manage', 'followups.work', 'followups.manage', 'member.portal', 'children.station', 'children.support', 'volunteering.serve', 'service_planning.read', 'service_planning.manage', 'forms.read', 'forms.manage', 'people.read', 'people.manage', 'families.read', 'families.manage', 'events.read', 'events.manage', 'life_events.read', 'life_events.manage', 'identity.manage', 'volunteering.manage', 'children.manage', 'giving.manage', 'attendance.manage', 'ministries.lead', 'ministries.manage', 'communications.manage'], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof SymfonyUser || 'active' !== $user->account()->status()->value) {
            return false;
        }
        $codes = $this->permissions->permissionCodesForUser($user->account()->id()->toString());

        return in_array($attribute, $codes, true) || 'followups.work' === $attribute && in_array('followups.manage', $codes, true) || 'volunteering.serve' === $attribute && in_array('volunteering.manage', $codes, true) || 'ministries.lead' === $attribute && in_array('ministries.manage', $codes, true) || str_ends_with($attribute, '.read') && in_array(substr($attribute, 0, -5).'.manage', $codes, true);
    }
}
