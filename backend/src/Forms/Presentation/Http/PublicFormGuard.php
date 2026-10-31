<?php

declare(strict_types=1);

namespace App\Forms\Presentation\Http;

use InvalidArgumentException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final readonly class PublicFormGuard
{
    public function __construct(private ClockInterface $clock, #[Autowire('%kernel.secret%')] private string $secret, #[Autowire(service: 'limiter.public_form_submit')] private RateLimiterFactoryInterface $limiter)
    {
    }

    public function challenge(string $token, int $version): string
    {
        $value = $this->clock->now()->getTimestamp().'.'.bin2hex(random_bytes(16));

        return $value.'.'.hash_hmac('sha256', $token.'|'.$version.'|'.$value, $this->secret);
    }

    public function limit(string $clientIp): void
    {
        if (!$this->limiter->create(hash('sha256', $clientIp))->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(900, 'Too many submissions. Please try again later.');
        }
    }

    public function check(string $token, int $version, string $challenge, string $website): void
    {
        if (!preg_match('/^(\d{10})\.([a-f0-9]{32})\.([a-f0-9]{64})$/D', $challenge, $parts)) {
            throw new InvalidArgumentException('Reload the form before submitting.');
        }
        $age = $this->clock->now()->getTimestamp() - (int) $parts[1];
        if ('' !== $website || $age < 2 || $age > 7200 || !hash_equals(hash_hmac('sha256', $token.'|'.$version.'|'.$parts[1].'.'.$parts[2], $this->secret), $parts[3])) {
            throw new InvalidArgumentException('The submission could not be accepted. Wait a moment or reload the form.');
        }
    }
}
