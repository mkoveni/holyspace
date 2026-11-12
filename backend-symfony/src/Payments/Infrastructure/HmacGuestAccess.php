<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure;

use App\Payments\Application\Contract\GuestAccess;
use SensitiveParameter;

final readonly class HmacGuestAccess implements GuestAccess
{
    public function __construct(#[SensitiveParameter] private string $secret)
    {
    }

    public function token(string $orderId): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', 'guest-access:'.strtolower($orderId), $this->secret, true)), '+/', '-_'), '=');
    }

    public function hash(#[SensitiveParameter] string $token): string
    {
        return hash('sha256', 'guest-access-token:'.$token);
    }

    public function emailHash(string $email): string
    {
        return hash_hmac('sha256', 'guest-email:'.strtolower(trim($email)), $this->secret);
    }
}
