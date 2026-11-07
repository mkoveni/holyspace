<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain;

final class IdentityRules
{
    /** A management grant implies the matching working permission. */
    private const array ADDITIONAL_PERMISSIONS = [
        'volunteering.manage' => 'volunteering.serve',
        'ministries.manage' => 'ministries.lead',
        'followups.manage' => 'followups.work',
    ];

    private function __construct()
    {
    }

    public static function username(?string $value): string
    {
        $normalized = self::normalize($value);
        if (1 !== preg_match('/\A[a-z0-9._-]{3,80}\z/', $normalized)) {
            throw IdentityException::invalid('Username must contain 3–80 letters, digits, dots, underscores or hyphens.');
        }

        return $normalized;
    }

    public static function email(?string $value): string
    {
        $normalized = self::normalize($value);
        if (mb_strlen($normalized) > 254 || 1 !== preg_match('/\A[^\s@]+@[^\s@]+\.[^\s@]+\z/u', $normalized)) {
            throw IdentityException::invalid('A valid email address is required.');
        }

        return $normalized;
    }

    public static function name(?string $value): string
    {
        $name = trim($value ?? '');
        if ('' === $name || mb_strlen($name) > 160) {
            throw IdentityException::invalid('Display name is required and must be at most 160 characters.');
        }

        return $name;
    }

    public static function password(?string $value): void
    {
        if (null === $value || mb_strlen($value) < 12 || strlen($value) > 72 || '' === trim($value)) {
            throw IdentityException::invalid('Password must contain at least 12 characters and at most 72 UTF-8 bytes.');
        }
    }

    public static function normalize(?string $value): string
    {
        return mb_strtolower(trim($value ?? ''));
    }

    /**
     * @param list<string> $granted
     *
     * @return list<string> sorted, de-duplicated permissions including implied ones
     */
    public static function effectivePermissions(array $granted): array
    {
        $effective = $granted;
        foreach ($granted as $code) {
            if (str_ends_with($code, '.manage')) {
                $effective[] = substr($code, 0, -7).'.read';
            }
            if (isset(self::ADDITIONAL_PERMISSIONS[$code])) {
                $effective[] = self::ADDITIONAL_PERMISSIONS[$code];
            }
        }
        $effective = array_values(array_unique($effective));
        sort($effective);

        return $effective;
    }
}
