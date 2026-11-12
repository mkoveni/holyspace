<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

use App\Payments\Application\Contract\HumanCheck;
use App\Payments\Domain\PaymentException;
use JsonException;
use SensitiveParameter;

/**
 * Cloudflare Turnstile server-side verification. With no secret configured it permits requests only outside production,
 * so a missing key can never silently open the public checkout.
 */
final readonly class TurnstileHumanCheck implements HumanCheck
{
    private const string ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private ProviderHttp $http,
        #[SensitiveParameter] private string $secret,
        private string $environment,
    ) {
    }

    public function passes(?string $token, string $clientAddress): bool
    {
        if ('' === $this->secret) {
            if ('prod' === $this->environment) {
                // A deployment gap, not a bot: say so, so the operator is told rather than every giver being called a robot.
                throw PaymentException::unavailable('Online giving is not fully set up yet. Please contact the church office.');
            }

            return true;
        }
        if (null === $token || '' === $token || \strlen($token) > 2048) {
            return false;
        }
        $body = http_build_query(['secret' => $this->secret, 'response' => $token, 'remoteip' => $clientAddress]);
        try {
            $answer = json_decode($this->http->send('POST', self::ENDPOINT, ['Content-Type' => 'application/x-www-form-urlencoded'], $body), true, 8, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw PaymentException::unavailable('Verification is temporarily unavailable. Try again.');
        }

        return \is_array($answer) && true === ($answer['success'] ?? false);
    }
}
