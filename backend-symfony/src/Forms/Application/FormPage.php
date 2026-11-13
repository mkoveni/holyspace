<?php

declare(strict_types=1);

namespace App\Forms\Application;

/** A form as served to a respondent: the questions without any answer key, and whether it still takes answers. */
final readonly class FormPage
{
    /** @param array{questions: list<array<string, mixed>>} $definition */
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $kind,
        public string $identity,
        public int $version,
        public array $definition,
        public bool $open,
        public int $retentionMonths,
        public bool $leaderboard,
        public ?string $challenge,
    ) {
    }
}
