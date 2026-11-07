<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Shared\Application;

/** A stable SHA-256 over length-prefixed values, so retries of the same request can be recognised without ambiguity. */
final class RequestFingerprint
{
    private function __construct()
    {
    }

    public static function of(string|int|float|bool|null ...$values): string
    {
        $source = '';
        foreach ($values as $value) {
            $text = match (true) {
                true === $value => 'true',
                false === $value => 'false',
                null === $value => 'null',
                default => (string) $value,
            };
            $source .= strlen($text).':'.$text;
        }

        return hash('sha256', $source);
    }
}
