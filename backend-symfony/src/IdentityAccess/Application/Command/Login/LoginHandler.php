<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\Login;

use App\IdentityAccess\Application\Contract\AccountReader;
use App\IdentityAccess\Application\Contract\LoginAttemptWriter;
use App\IdentityAccess\Application\Contract\PasswordHasher;
use App\IdentityAccess\Application\Contract\SessionWriter;
use App\IdentityAccess\Application\Contract\TokenCodec;
use App\IdentityAccess\Domain\Account;
use App\IdentityAccess\Domain\IdentityException;
use App\IdentityAccess\Domain\IdentityRules;
use App\IdentityAccess\Domain\Repository\AccountRepository;
use App\Shared\Application\IdGenerator;
use App\Shared\Application\UnitOfWork;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * The transaction never throws for a failed attempt: it returns the outcome so the throttle
 * counters commit, and only then is the authentication failure raised.
 */
final readonly class LoginHandler
{
    public function __construct(
        private AccountReader $accounts,
        private AccountRepository $repository,
        private LoginAttemptWriter $attempts,
        private SessionWriter $sessions,
        private PasswordHasher $passwords,
        private TokenCodec $tokens,
        private UnitOfWork $transactions,
        private ClockInterface $clock,
        private IdGenerator $ids,
        private int $sessionLifetimeSeconds,
    ) {
    }

    public function handle(LoginCommand $command): LoginResult
    {
        $identifier = IdentityRules::normalize($command->username);
        $key = $this->tokens->hash($identifier);

        /** @var array{result: ?LoginResult, throttled: bool} $outcome */
        $outcome = $this->transactions->execute(fn (): array => $this->authenticate($command, $identifier, $key));

        if ($outcome['throttled']) {
            throw IdentityException::throttled('Too many login attempts. Try again in one minute.');
        }

        return $outcome['result'] ?? throw IdentityException::unauthenticated('Invalid username or password.');
    }

    /** @return array{result: ?LoginResult, throttled: bool} */
    private function authenticate(LoginCommand $command, string $identifier, string $key): array
    {
        $this->attempts->lockLogin($key);
        $now = $this->clock->now();
        if ($this->attempts->throttled($key, $now)) {
            return ['result' => null, 'throttled' => true];
        }

        $found = $this->accounts->findByLogin($identifier);
        $account = null === $found ? null : $this->repository->findForUpdate($found->id);

        // Always verify a hash so unknown accounts cost the same as wrong passwords.
        $matched = $this->passwords->matches($command->password, $account?->passwordHash->value ?? $this->passwords->dummyHash());

        if (null !== $account && $account->isActive() && $matched) {
            return ['result' => $this->createSession($account, $key, $now, $command->deviceName), 'throttled' => false];
        }

        $this->attempts->failed($key, $now);

        return ['result' => null, 'throttled' => false];
    }

    private function createSession(Account $account, string $key, DateTimeImmutable $now, string $deviceName): LoginResult
    {
        $this->attempts->clearFailures($key);
        $token = $this->tokens->generate();
        $this->sessions->create(
            $this->ids->uuid(),
            $account->id,
            $this->tokens->hash($token),
            $now->modify(sprintf('+%d seconds', $this->sessionLifetimeSeconds)),
            $now,
            $deviceName,
        );

        return new LoginResult($token);
    }
}
