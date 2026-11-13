<?php

declare(strict_types=1);

namespace App\Forms\Domain;

final readonly class Score
{
    /** @param list<string> $correctQuestionIds */
    public function __construct(public int $score, public int $maxScore, public array $correctQuestionIds)
    {
    }
}
