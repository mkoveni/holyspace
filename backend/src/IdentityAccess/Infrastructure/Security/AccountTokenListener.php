<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler;
use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateQuery;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\ValueObject\UserId;
use InvalidArgumentException;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class AccountTokenListener
{
    public function __construct(
        private UserAccountRepository $users,
        private ClockInterface $clock,
        private GetAccountSecurityStateHandler $getAccountSecurityStateHandler,
    ) {
    }

    #[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
    public function created(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof SymfonyUser) {
            return;
        }
        $account = $user->account();
        $state = ($this->getAccountSecurityStateHandler)(new GetAccountSecurityStateQuery($account->id()->toString()));
        $data = $event->getData();
        $data['account_id'] = $account->id()->toString();
        $data['security_version'] = (int) $state['version'];
        $data['password_fingerprint'] = hash('sha256', $account->passwordHash()->value());
        $data['mfa_verified'] = ($data['mfa_verified'] ?? false) === true && null !== $state['secret'];
        if (null !== $state['secret'] && !$data['mfa_verified']) {
            $data['iat'] ??= $this->clock->now()->getTimestamp();
            $data['exp'] = min($data['exp'] ?? $data['iat'] + 300, $data['iat'] + 300);
        }
        $event->setData($data);
    }

    #[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_decoded')]
    public function decoded(JWTDecodedEvent $event): void
    {
        $data = $event->getPayload();
        if (!isset($data['account_id'], $data['security_version'], $data['password_fingerprint'])) {
            $event->markAsInvalid();

            return;
        }
        try {
            $user = $this->users->findById(UserId::fromString($data['account_id']));
        } catch (InvalidArgumentException) {
            $event->markAsInvalid();

            return;
        }
        if (!$user || $user->username()->value() !== ($data['username'] ?? null) || 'active' !== $user->status()->value || !hash_equals(hash('sha256', $user->passwordHash()->value()), $data['password_fingerprint']) || (int) ($this->getAccountSecurityStateHandler)(new GetAccountSecurityStateQuery($data['account_id']))['version'] !== $data['security_version']) {
            $event->markAsInvalid();
        }
    }

    #[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_authenticated')]
    public function authenticated(JWTAuthenticatedEvent $event): void
    {
        $event->getToken()->setAttribute('mfa_verified', ($event->getPayload()['mfa_verified'] ?? false) === true);
    }
}
