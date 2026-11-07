<?php

declare(strict_types=1);

namespace App\Communications\Application;

/** SHA-256 over length-prefixed parts, so receipts with the same words in different fields never collide. */
final class DeliveryFingerprint
{
    private function __construct()
    {
    }

    public static function of(string ...$values): string
    {
        $source = '';
        foreach ($values as $text) {
            // Lengths count UTF-16 code units, as the Java implementation did, so stored hashes stay comparable.
            $source .= intdiv(\strlen((string) mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2).':'.$text;
        }

        return hash('sha256', $source);
    }
}
