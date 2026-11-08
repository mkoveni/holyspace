<?php

declare(strict_types=1);

namespace App\Payments\Application;

use App\Shared\Domain\CalendarDate;
use App\Shared\Domain\JavaString;

/**
 * Java `PaymentHash`: SHA-256 over, for each value, the UTF-8 bytes of "<length>:<text>", where length counts UTF-16
 * code units, null is "<null>" and other values use Java's toString (UUIDs lower-case, dates ISO). Request, notification
 * and review hashes written by Java are compared on retry: keep the Java-captured vectors in PaymentHashTest green.
 */
final class PaymentHash
{
    private function __construct()
    {
    }

    public static function of(string|int|bool|CalendarDate|null ...$values): string
    {
        $context = hash_init('sha256');
        foreach ($values as $value) {
            $text = match (true) {
                null === $value => '<null>',
                true === $value => 'true',
                false === $value => 'false',
                $value instanceof CalendarDate => $value->iso(),
                default => (string) $value,
            };
            hash_update($context, JavaString::length($text).':'.$text);
        }

        return hash_final($context);
    }
}
