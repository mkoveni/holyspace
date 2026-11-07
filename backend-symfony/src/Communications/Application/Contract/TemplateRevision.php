<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

use DateTimeImmutable;

final readonly class TemplateRevision
{
    /** @param list<string> $variables */
    public function __construct(public int $version, public string $subject, public string $html, public array $variables, public DateTimeImmutable $publishedAt)
    {
    }
}
