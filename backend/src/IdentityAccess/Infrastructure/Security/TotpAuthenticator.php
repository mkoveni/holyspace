<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use App\IdentityAccess\Application\Port\Authenticator;
use DomainException;
use OTPHP\TOTP;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(Authenticator::class)]
final readonly class TotpAuthenticator implements Authenticator
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function create(string $username): array
    {
        $otp = TOTP::generate($this->clock);
        $otp->setLabel($username);
        $otp->setIssuer('Divine Community Space');

        return ['secret' => $otp->getSecret(), 'uri' => $otp->getProvisioningUri()];
    }

    public function step(string $secret, string $code, int $last): int
    {
        if (!preg_match('/^[0-9]{6}$/D', $code)) {
            throw new DomainException('Authenticator code is invalid or already used.');
        }
        $otp = TOTP::createFromSecret($secret, $this->clock);
        $now = intdiv($this->clock->now()->getTimestamp(), 30);
        foreach ([$now, $now - 1, $now + 1] as $step) {
            if ($step > $last && hash_equals($otp->at($step * 30), $code)) {
                return $step;
            }
        }
        throw new DomainException('Authenticator code is invalid or already used.');
    }
}
