<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Lets controllers write #[IsGranted('identity.manage')]; the permission codes are the attributes.
 *
 * @extends Voter<string, mixed>
 */
final class PermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return 1 === preg_match('/\A[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+\z/', $attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof SessionUser && in_array($attribute, $user->permissions, true);
    }
}
