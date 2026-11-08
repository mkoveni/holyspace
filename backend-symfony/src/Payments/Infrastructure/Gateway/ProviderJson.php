<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

use App\Payments\Domain\PaymentException;
use JsonException;

/** Jackson-like reads of provider JSON: missing paths are null, `text` is `asText()` ("" for missing or containers). */
final class ProviderJson
{
    private function __construct()
    {
    }

    /** @throws PaymentException 503 with $failure when the body is not JSON */
    public static function parse(string $body, string $failure): mixed
    {
        try {
            return json_decode($body, true, 32, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            throw PaymentException::unavailable($failure);
        }
    }

    public static function path(mixed $node, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (!\is_array($node) || array_is_list($node) && [] !== $node || !\array_key_exists($key, $node)) {
                return null;
            }
            $node = $node[$key];
        }

        return $node;
    }

    public static function text(mixed $node, string $default = ''): string
    {
        return match (true) {
            null === $node => $default,
            \is_string($node) => $node,
            true === $node => 'true',
            false === $node => 'false',
            \is_int($node), \is_float($node) => (string) $node,
            default => '',
        };
    }
}
