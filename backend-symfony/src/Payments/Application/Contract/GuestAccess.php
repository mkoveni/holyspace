<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use SensitiveParameter;

/**
 * Credentials of a public (guest) payment. The access token is derived from the order id with a server secret, so a
 * retried request returns the same token while nothing secret is stored: only its hash is, and it never appears in a URL.
 */
interface GuestAccess
{
    public function token(string $orderId): string;

    public function hash(#[SensitiveParameter] string $token): string;

    /** A keyed hash of the normalised email, for throttling and lookups without keeping the address in the clear. */
    public function emailHash(string $email): string;
}
