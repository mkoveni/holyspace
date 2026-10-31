<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\Shared\Application\Security\AccessControl;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[AsAlias(AccessControl::class)]
final readonly class SymfonyAccessControl implements AccessControl
{
    public function __construct(private AuthorizationCheckerInterface $access)
    {
    }

    public function isGranted(string $permission, mixed $subject = null): bool
    {
        return $this->access->isGranted($permission, $subject);
    }
}
