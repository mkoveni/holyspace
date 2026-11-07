<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

use DateTimeImmutable;

final readonly class TemplateView
{
    /** @param list<string> $variables */
    public function __construct(
        public string $id,
        public string $key,
        public string $name,
        public string $subject,
        public string $html,
        public array $variables,
        public string $status,
        public int $version,
        public ?int $publishedVersion,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
