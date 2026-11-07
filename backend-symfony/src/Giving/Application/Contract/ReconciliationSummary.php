<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

/** A batch with its current reversed and net amounts, review version and latest review (Java `ReconciliationReader.Summary`). */
final readonly class ReconciliationSummary
{
    private function __construct(
        public ReconciliationBatchView $batch,
        public int $reversedMinor,
        public int $currentNetMinor,
        public int $version,
        public ?ReconciliationReviewView $review,
        public bool $needsReview,
    ) {
    }

    /**
     * Before any review a batch needs one when its amounts differ or something was reversed since; afterwards when the
     * latest review is not EXPLAINED or was written against a different reversed amount.
     *
     * @param list<ReconciliationItem> $items
     */
    public static function of(ReconciliationBatchView $batch, array $items, ?ReconciliationReviewView $review): self
    {
        $reversed = array_sum(array_map(static fn (ReconciliationItem $item): int => $item->reversedMinor(), $items));
        $needsReview = null === $review
            ? $batch->expectedMinor !== $batch->actualMinor || $reversed > 0
            : 'EXPLAINED' !== $review->outcome || $review->reversedMinor !== $reversed;

        return new self($batch, $reversed, $batch->actualMinor - $reversed, $review->version ?? 0, $review, $needsReview);
    }
}
