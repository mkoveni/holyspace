<?php

declare(strict_types=1);

namespace App\Communications\Domain;

/** A subject and an HTML body with every variable replaced. */
final readonly class RenderedContent
{
    public function __construct(public string $subject, public string $html)
    {
    }
}
