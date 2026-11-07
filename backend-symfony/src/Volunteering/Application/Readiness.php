<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

final readonly class Readiness
{
    /** @param list<string> $issues */
    public function __construct(public bool $ready, public array $issues)
    {
    }
}
