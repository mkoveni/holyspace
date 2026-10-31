<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Http;

use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler;
use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateQuery;
use App\IdentityAccess\Application\Query\IsAccountVerified\IsAccountVerifiedHandler;
use App\IdentityAccess\Application\Query\IsAccountVerified\IsAccountVerifiedQuery;
use App\IdentityAccess\Infrastructure\Security\SymfonyUser;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: 'kernel.controller', priority: 200)]
final readonly class MfaAccessListener
{
    public function __construct(
        private TokenStorageInterface $tokens,
        private GetAccountSecurityStateHandler $getAccountSecurityStateHandler,
        private IsAccountVerifiedHandler $isAccountVerifiedHandler,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/api/') || in_array($path, ['/api/me', '/api/account-recovery'], true) || preg_match('#^/api/account-security(?:/|$)#', $path)) {
            return;
        }
        $user = $this->tokens->getToken()?->getUser();
        if ($user instanceof SymfonyUser && null !== ($this->getAccountSecurityStateHandler)(new GetAccountSecurityStateQuery($user->account()->id()->toString()))['secret'] && !($this->isAccountVerifiedHandler)(new IsAccountVerifiedQuery())) {
            $event->setController(static fn () => new JsonResponse(['message' => 'Authenticator verification is required.', 'mfaRequired' => true], 403, ['Cache-Control' => 'private, no-store']));
        }
    }
}
