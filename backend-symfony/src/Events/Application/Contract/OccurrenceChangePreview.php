<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class OccurrenceChangePreview
{
    /**
     * @param list<ChangePreviewItem> $changes
     * @param list<string>            $skipped
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
