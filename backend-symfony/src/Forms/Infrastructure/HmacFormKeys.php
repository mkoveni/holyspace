<?php

declare(strict_types=1);

namespace App\Forms\Infrastructure;

use App\Forms\Application\Contract\FormKeys;
use DateTimeImmutable;
use SensitiveParameter;

final readonly class HmacFormKeys implements FormKeys
{
    /** A page challenge is good for two hours. */
    private const int CHALLENGE_SECONDS = 7200;

    public function __construct(#[SensitiveParameter] private string $secret)
    {
    }

    public function tokenHash(#[SensitiveParameter] string $token): string
    {
        return hash('sha256', 'form-token:'.$token);
    }

    public function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function submissionKey(string $formId, #[SensitiveParameter] string $clientKey): string
    {
        return hash_hmac('sha256', 'form-submission:'.strtolower($formId).'|'.$clientKey, $this->secret);
    }

    public function participationMark(string $formId, string $accountId): string
    {
        return hash_hmac('sha256', 'form-mark:'.strtolower($formId).'|'.strtolower($accountId), $this->secret);
    }

    public function challenge(string $formId, DateTimeImmutable $now): string
    {
        $issued = (string) $now->getTimestamp();

        return $issued.'.'.$this->sign($formId, $issued);
    }

    public function challengeIsValid(string $formId, #[SensitiveParameter] string $challenge, DateTimeImmutable $now): bool
    {
        if (1 !== preg_match('/\A(\d{1,12})\.([0-9a-f]{64})\z/', $challenge, $parts)) {
            return false;
        }
        $age = $now->getTimestamp() - (int) $parts[1];

        return $age >= 0 && $age <= self::CHALLENGE_SECONDS && hash_equals($this->sign($formId, $parts[1]), $parts[2]);
    }

    private function sign(string $formId, string $issued): string
    {
        return hash_hmac('sha256', 'form-challenge:'.strtolower($formId).'|'.$issued, $this->secret);
    }
}
