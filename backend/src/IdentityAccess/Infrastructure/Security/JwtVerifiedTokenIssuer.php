<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use App\IdentityAccess\Application\Port\VerifiedTokenIssuer;
use App\IdentityAccess\Domain\Model\UserAccount;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(VerifiedTokenIssuer::class)]
final readonly class JwtVerifiedTokenIssuer implements VerifiedTokenIssuer
{
    public function __construct(private JWTTokenManagerInterface $jwt)
    {
    }

    public function issue(UserAccount $account): string
    {
        return $this->jwt->createFromPayload(new SymfonyUser($account), ['mfa_verified' => true]);
    }
}
