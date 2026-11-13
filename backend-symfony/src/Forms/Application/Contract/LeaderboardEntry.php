<?php

declare(strict_types=1);

namespace App\Forms\Application\Contract;

final readonly class LeaderboardEntry
{
    public function __construct(public int $rank, public string $nickname, public int $score, public int $maxScore)
    {
    }
}
