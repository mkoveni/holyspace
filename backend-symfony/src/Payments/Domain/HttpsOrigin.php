<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use App\Shared\Domain\JavaString;

/**
 * The origins `java.net.URI` accepts for provider callbacks and browser returns: scheme exactly `https`, a server-based
 * host (DNS name, IPv4 or bracketed IPv6) with an optional port, no user info, query or fragment, and no path beyond
 * one `/`. Trailing slashes are removed, nothing else is normalised.
 */
final class HttpsOrigin
{
    private const string LABEL = '[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?';
    private const string TOP = '[A-Za-z](?:[A-Za-z0-9-]*[A-Za-z0-9])?';
    private const string IPV4 = '(?:\d{1,3}\.){3}\d{1,3}';
    private const string IPV6 = '\[[0-9A-Fa-f:.]+\]';

    private function __construct()
    {
    }

    /** The host when $value is an acceptable origin, null otherwise. */
    public static function host(string $value): ?string
    {
        $host = '(?<host>(?:'.self::LABEL.'\.)*'.self::TOP.'\.?|'.self::IPV4.'|'.self::IPV6.')';
        if (1 !== preg_match('#\Ahttps://'.$host.'(?::\d*)?/?\z#', $value, $parts)) {
            return null;
        }

        return $parts['host'];
    }

    /** Java `ProviderConfiguration.origin`: also refuses loopback hosts, which a provider can never reach. */
    public static function public(?string $value): string
    {
        $origin = JavaString::trim($value ?? '');
        $host = self::host($origin);
        if (null === $host || 0 === strcasecmp($host, 'localhost') || '127.0.0.1' === $host || '[::1]' === $host) {
            throw PaymentException::invalid('Use a public HTTPS origin without a path, query or fragment.');
        }

        return rtrim($origin, '/');
    }
}
