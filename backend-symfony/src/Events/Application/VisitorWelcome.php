<?php

declare(strict_types=1);

namespace App\Events\Application;

/** What a visitor is told afterwards. It never repeats stored details, so the form cannot be used to look people up. */
final readonly class VisitorWelcome
{
    public function __construct(public string $title, public bool $admitted)
    {
    }
}
