<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Lets controllers write #[IsGranted('identity.manage')]; the permission codes are the attributes. Codes joined with
 * `|` grant access when the caller holds any one of them, like Spring's `hasAnyAuthority`.
 *
 * @extends Voter<string, mixed>
 */
final class PermissionVoter extends Voter
{
    private const string CODE = '[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return 1 === preg_match('/\A'.self::CODE.'(\|'.self::CODE.')*\z/', $attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof SessionUser && [] !== array_intersect(explode('|', $attribute), $user->permissions);
    }
}
