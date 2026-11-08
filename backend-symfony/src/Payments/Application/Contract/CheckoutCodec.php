<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

interface CheckoutCodec
{
    /** @param array<string, string> $fields */
    public function encode(array $fields): string;

    /** @return array<string, string> */
    public function decode(?string $fields): array;
}
