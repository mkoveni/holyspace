<?php

declare(strict_types=1);

namespace App\Forms\Application;

use App\Forms\Domain\FormSettings;
use DateTimeImmutable;

/** The editable fields of a form as a request carries them, before the domain validates them. */
final readonly class FormDraft
{
    /**
     * @param list<string> $groupIds
     * @param array<mixed> $definition
     */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public string $kind,
        public string $audience,
        public string $identity,
        public ?DateTimeImmutable $opensAt,
        public ?DateTimeImmutable $closesAt,
        public ?int $maxResponses,
        public int $retentionMonths,
        public bool $leaderboardEnabled,
        public int $leaderboardSize,
        public bool $showScore,
        public bool $showAnswers,
        public array $groupIds,
        public array $definition,
    ) {
    }

    public function settings(): FormSettings
    {
        return FormSettings::of(
            $this->title,
            $this->description,
            $this->kind,
            $this->audience,
            $this->identity,
            $this->opensAt,
            $this->closesAt,
            $this->maxResponses,
            $this->retentionMonths,
            $this->leaderboardEnabled,
            $this->leaderboardSize,
            $this->showScore,
            $this->showAnswers,
            $this->groupIds,
            $this->definition,
        );
    }
}
