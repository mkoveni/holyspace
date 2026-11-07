<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

/** A batch with its gifts and latest 100 reviews, newest first (Java `ReconciliationReader.Detail`). */
final readonly class ReconciliationDetail
{
    /**
     * @param list<ReconciliationItem>       $items
     * @param list<ReconciliationReviewView> $reviews
     */
    public function __construct(
        public ReconciliationSummary $summary,
        public array $items,
        public array $reviews,
    ) {
    }
}
