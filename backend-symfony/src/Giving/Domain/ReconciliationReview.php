<?php

declare(strict_types=1);

namespace App\Giving\Domain;

use DateTimeImmutable;

/**
 * Immutable review evidence for a reconciliation batch. Each review is the batch's next version and records the reversed
 * amount it was written against, so a later reversal reopens the batch for review.
 */
final readonly class ReconciliationReview
{
    public const string FOLLOW_UP = 'FOLLOW_UP';
    public const string EXPLAINED = 'EXPLAINED';
    public const array OUTCOMES = [self::FOLLOW_UP, self::EXPLAINED];

    public string $note;
    public string $outcome;

    private function __construct(
        public string $id,
        public string $batchId,
        public int $version,
        ?string $outcome,
        ?string $note,
        public int $reversedMinor,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
    ) {
        $this->note = GivingRules::text($note, 1000, 'Review evidence');
        if (!\in_array($outcome, self::OUTCOMES, true)) {
            throw GivingException::invalid('Choose follow-up or explained.');
        }
        $this->outcome = $outcome;
    }

    /** Created only through Reconciliation::review, which assigns the next version. */
    public static function record(string $id, string $batchId, int $version, ?string $outcome, ?string $note, int $reversedMinor, string $createdBy, DateTimeImmutable $createdAt): self
    {
        return new self($id, $batchId, $version, $outcome, $note, $reversedMinor, $createdBy, $createdAt);
    }
}
