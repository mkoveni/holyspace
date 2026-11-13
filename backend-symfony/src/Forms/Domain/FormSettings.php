<?php

declare(strict_types=1);

namespace App\Forms\Domain;

use DateTimeImmutable;

/** Everything a staff member chooses about a form. Validated once here; the aggregate only stores it. */
final readonly class FormSettings
{
    public const int DEFAULT_RETENTION_MONTHS = 3;

    /**
     * @param list<string>                                 $groupIds
     * @param array{questions: list<array<string, mixed>>} $definition the validated draft, in stored shape
     */
    private function __construct(
        public string $title,
        public string $description,
        public FormKind $kind,
        public Audience $audience,
        public IdentityMode $identity,
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

    /**
     * @param list<string> $groupIds
     * @param array<mixed> $definition
     */
    public static function of(
        ?string $title,
        ?string $description,
        string $kind,
        string $audience,
        string $identity,
        ?DateTimeImmutable $opensAt,
        ?DateTimeImmutable $closesAt,
        ?int $maxResponses,
        int $retentionMonths,
        bool $leaderboardEnabled,
        int $leaderboardSize,
        bool $showScore,
        bool $showAnswers,
        array $groupIds,
        array $definition,
    ): self {
        $title = trim((string) $title);
        $description = trim((string) $description);
        if ('' === $title || mb_strlen($title) > 160 || mb_strlen($description) > 2000) {
            throw FormException::invalid('A form needs a title of at most 160 characters and a description of at most 2000.');
        }
        $kindEnum = FormKind::tryFrom($kind) ?? throw FormException::invalid('Unknown form kind.');
        $audienceEnum = Audience::tryFrom($audience) ?? throw FormException::invalid('Unknown audience.');
        $identityEnum = IdentityMode::tryFrom($identity) ?? throw FormException::invalid('Unknown identity mode.');
        if (Audience::Public === $audienceEnum && IdentityMode::Anonymous !== $identityEnum) {
            throw FormException::invalid('A public form is always anonymous.');
        }
        $groupIds = array_values(array_unique(array_map('strtolower', $groupIds)));
        if (Audience::Groups === $audienceEnum ? ([] === $groupIds || count($groupIds) > 50) : [] !== $groupIds) {
            throw FormException::invalid('Choose between 1 and 50 groups, and only for a group form.');
        }
        if (null !== $opensAt && null !== $closesAt && $closesAt <= $opensAt) {
            throw FormException::invalid('A form must close after it opens.');
        }
        if (null !== $maxResponses && $maxResponses < 1) {
            throw FormException::invalid('The response limit must be at least 1.');
        }
        if ($retentionMonths < 1 || $retentionMonths > self::DEFAULT_RETENTION_MONTHS) {
            throw FormException::invalid('Responses are kept for 1 to 3 months.');
        }
        $quiz = FormKind::Quiz === $kindEnum;
        if (!$quiz && ($leaderboardEnabled || $showScore || $showAnswers)) {
            throw FormException::invalid('Scores, answers and leaderboards belong to quizzes.');
        }
        if ($leaderboardSize < 1 || $leaderboardSize > 100) {
            throw FormException::invalid('A leaderboard shows 1 to 100 entries.');
        }

        return new self(
            $title,
            $description,
            $kindEnum,
            $audienceEnum,
            $identityEnum,
            $opensAt,
            $closesAt,
            $maxResponses,
            $retentionMonths,
            $leaderboardEnabled,
            $leaderboardSize,
            $showScore,
            $showAnswers,
            $groupIds,
            FormDefinition::fromArray($definition, $quiz)->toArray(),
        );
    }

    public function isQuiz(): bool
    {
        return FormKind::Quiz === $this->kind;
    }
}
