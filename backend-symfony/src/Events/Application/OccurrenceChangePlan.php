<?php

declare(strict_types=1);

namespace App\Events\Application;

/** The outcome of reviewing a draft: the changes to write, the dates left alone, and the hash proving the review. */
final readonly class OccurrenceChangePlan
{
    /**
     * @param list<OccurrenceChange> $changes
     * @param list<string>           $skipped
     */
    public function __construct(
        public string $hash,
        public array $changes,
        public array $skipped,
        public int $confirmedAffected,
        public int $waitlistedAffected,
        public int $attendedAffected,
    ) {
    }
}
