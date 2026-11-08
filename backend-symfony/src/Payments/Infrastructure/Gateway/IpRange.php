<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

/**
 * Spring Security's `IpAddressMatcher`: an IP literal with an optional prefix length. Addresses of the other family
 * never match.
 */
final readonly class IpRange
{
    private function __construct(private string $network, private int $bits)
    {
    }

    /** null when Spring would reject the range (not an IP literal, or a prefix longer than the address). */
    public static function parse(string $range): ?self
    {
        $parts = explode('/', $range, 2);
        $network = self::packed($parts[0]);
        if (null === $network) {
            return null;
        }
        $bits = \strlen($network) * 8;
        if (isset($parts[1])) {
            if (1 !== preg_match('/\A[0-9]{1,3}\z/', $parts[1]) || (int) $parts[1] > $bits) {
                return null;
            }
            $bits = (int) $parts[1];
        }

        return new self($network, $bits);
    }

    public function matches(string $ip): bool
    {
        $address = self::packed($ip);
        if (null === $address || \strlen($address) !== \strlen($this->network)) {
            return false;
        }
        $whole = intdiv($this->bits, 8);
        if (substr($address, 0, $whole) !== substr($this->network, 0, $whole)) {
            return false;
        }
        $rest = $this->bits % 8;
        if (0 === $rest) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (\ord($address[$whole]) & $mask) === (\ord($this->network[$whole]) & $mask);
    }

    private static function packed(string $address): ?string
    {
        if (str_starts_with($address, '[') && str_ends_with($address, ']')) {
            $address = substr($address, 1, -1);
        }
        if (false === filter_var($address, \FILTER_VALIDATE_IP)) {
            return null;
        }
        $packed = inet_pton($address);

        return false === $packed ? null : $packed;
    }
}
