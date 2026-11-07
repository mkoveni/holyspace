<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

/** A published template rendered and sanitized for sending. */
final readonly class RenderedEmail
{
    public function __construct(public string $key, public int $version, public string $subject, public string $html)
    {
    }
}
