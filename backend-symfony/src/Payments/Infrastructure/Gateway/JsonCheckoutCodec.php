<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

use App\Payments\Application\Contract\CheckoutCodec;
use JsonException;
use stdClass;

/** Checkout fields as a JSON object in signing order, as Java stores `checkout_fields`. */
final readonly class JsonCheckoutCodec implements CheckoutCodec
{
    public function encode(array $fields): string
    {
        return json_encode([] === $fields ? new stdClass() : $fields, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    public function decode(?string $fields): array
    {
        if (null === $fields) {
            return [];
        }
        try {
            $decoded = json_decode($fields, true, 2, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }
        $values = [];
        foreach (\is_array($decoded) ? $decoded : [] as $key => $value) {
            if (\is_string($value)) {
                $values[(string) $key] = $value;
            }
        }

        return $values;
    }
}
