<?php

declare(strict_types=1);

namespace App\Communications\Domain;

final class CommunicationRules
{
    private function __construct()
    {
    }

    /** A non-blank value of at most `$max` characters, trimmed. */
    public static function text(?string $value, int $max): string
    {
        $stripped = null === $value ? '' : (preg_replace('/\A[\s\p{Z}]+|[\s\p{Z}]+\z/u', '', $value) ?? '');
        if (null === $value || '' === $stripped || mb_strlen($value) > $max) {
            throw CommunicationException::invalid(sprintf('Enter text of at most %d characters.', $max));
        }

        return $stripped;
    }

    public static function version(int $actual, int $expected): void
    {
        if ($actual !== $expected) {
            throw CommunicationException::conflict('This record changed. Refresh before saving.');
        }
    }
}
