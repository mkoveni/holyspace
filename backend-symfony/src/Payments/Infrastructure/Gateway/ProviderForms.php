<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

/** Payfast's form encoding and signatures: `URLEncoder.encode` with `*` as `%2A` (PHP's urlencode), then MD5. */
final class ProviderForms
{
    private function __construct()
    {
    }

    /** @param array<string, string> $fields */
    public static function form(array $fields): string
    {
        $pairs = [];
        foreach ($fields as $key => $value) {
            $pairs[] = urlencode((string) $key).'='.urlencode($value);
        }

        return implode('&', $pairs);
    }

    /**
     * Hosted checkout signature: non-empty values trimmed, in field order, then the trimmed passphrase.
     *
     * @param array<string, string> $fields
     */
    public static function signature(array $fields, string $passphrase): string
    {
        $signed = [];
        foreach ($fields as $key => $value) {
            if ('' !== $value) {
                $signed[$key] = trim($value);
            }
        }

        return md5(self::form($signed).'&passphrase='.urlencode(trim($passphrase)));
    }

    /**
     * Payfast API headers: merchant-id, version and timestamp, signed with the passphrase in sorted key order.
     *
     * @return array<string, string>
     */
    public static function apiHeaders(string $merchantId, string $passphrase, string $timestamp): array
    {
        $headers = ['merchant-id' => $merchantId, 'version' => 'v1', 'timestamp' => $timestamp];
        $signed = $headers + ['passphrase' => $passphrase];
        ksort($signed, \SORT_STRING);
        $headers['signature'] = md5(self::form($signed));

        return $headers;
    }
}
