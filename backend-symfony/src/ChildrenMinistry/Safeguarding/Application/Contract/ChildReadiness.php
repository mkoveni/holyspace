<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Contract;

/** Programme preparation of children, owned by Safeguarding. A snapshot, never a substitute for session admission checks. */
interface ChildReadiness
{
    /**
     * @param list<string> $childIds
     *
     * @return array<string, ReadinessSummary> summaries keyed by child id; children that cannot be assessed are absent
     */
    public function summaries(string $programmeId, array $childIds): array;

    /**
     * The same assessment with its steps and the programme's time zone.
     *
     * @param list<string> $childIds
     *
     * @return array<string, ReadinessDetail> keyed by child id; children that cannot be assessed are absent
     */
    public function details(string $programmeId, array $childIds): array;
}
