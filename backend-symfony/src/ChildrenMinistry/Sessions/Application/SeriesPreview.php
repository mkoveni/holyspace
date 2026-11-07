<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use DateTimeImmutable;

final readonly class SeriesPreview
{
    /**
     * @param list<PreviewTeamMember> $team
     * @param list<PreviewOccurrence> $occurrences
     */
    public function __construct(public string $className, public string $roomName, public string $timeZone, public int $seatLimit, public array $team, public array $occurrences, public DateTimeImmutable $checkedAt)
    {
    }

    public function hasConflicts(): bool
    {
        foreach ($this->occurrences as $occurrence) {
            if ([] !== $occurrence->conflicts) {
                return true;
            }
        }

        return false;
    }
}
