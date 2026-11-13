<?php

declare(strict_types=1);

namespace App\Forms\Application\Contract;

use DateTimeImmutable;

/** Staff detail, including the draft and its answer key. Only handlers behind forms.read return it. */
final readonly class FormView
{
    /**
     * @param list<string>         $groupIds
     * @param array<string, mixed> $draft
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $kind,
        public string $audience,
        public string $identity,
        public string $status,
        public ?DateTimeImmutable $opensAt,
        public ?DateTimeImmutable $closesAt,
        public ?int $maxResponses,
        public int $retentionMonths,
        public bool $leaderboardEnabled,
        public int $leaderboardSize,
        public bool $showScore,
        public bool $showAnswers,
        public array $groupIds,
        public array $draft,
        public int $publishedVersion,
        public int $responseCount,
        public ?DateTimeImmutable $nextPurgeOn,
        public int $version,
    ) {
    }
}
