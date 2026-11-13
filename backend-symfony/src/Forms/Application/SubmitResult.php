<?php

declare(strict_types=1);

namespace App\Forms\Application;

/** What a respondent is told after submitting: a score and the answer key only when the form was set to show them. */
final readonly class SubmitResult
{
    /**
     * @param array<string, list<string>>|null $answerKey
     */
    public function __construct(public ?int $score, public ?int $maxScore, public ?array $answerKey)
    {
    }

    public static function received(): self
    {
        return new self(null, null, null);
    }
}
