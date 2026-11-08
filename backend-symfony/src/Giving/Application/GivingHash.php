<?php

declare(strict_types=1);

namespace App\Giving\Application;

use App\Giving\Domain\Allocation;
use App\Giving\Domain\Money;
use App\Shared\Domain\CalendarDate;
use App\Shared\Domain\JavaString;

/**
 * Request fingerprint shared with the Java application: SHA-256 over, for each field, the UTF-8 bytes of
 * "<length>:<value>", where length counts UTF-16 code units, null is "<null>" and other values use Java's
 * toString (a list renders as java.util.List: "[a, b]"). Stored request hashes are compared on retry, possibly against a row Java wrote, so this must not drift.
 * It is a different algorithm from the Events fingerprint; do not merge them.
 */
final class GivingHash
{
    private function __construct()
    {
    }

    /** @param string|int|bool|CalendarDate|Money|list<Allocation|string>|null ...$fields */
    public static function of(string|int|bool|CalendarDate|Money|array|null ...$fields): string
    {
        $context = hash_init('sha256');
        foreach ($fields as $field) {
            $value = self::render($field);
            hash_update($context, JavaString::length($value).':'.$value);
        }

        return hash_final($context);
    }

    /** @param string|int|bool|CalendarDate|Money|list<Allocation|string>|null $field */
    private static function render(string|int|bool|CalendarDate|Money|array|null $field): string
    {
        return match (true) {
            null === $field => '<null>',
            true === $field => 'true',
            false === $field => 'false',
            $field instanceof CalendarDate => $field->iso(),
            $field instanceof Money => $field->javaString(),
            \is_array($field) => '['.implode(', ', array_map(static fn (Allocation|string $item): string => $item instanceof Allocation ? $item->javaString() : $item, $field)).']',
            default => (string) $field,
        };
    }
}
