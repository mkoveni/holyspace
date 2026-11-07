<?php

declare(strict_types=1);

namespace App\Giving\Domain;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/**
 * A frozen bank or cash batch of 1-100 distinct gifts in one currency. The actual amount is the sum of the gifts at
 * creation; a difference from the expected amount needs an explanation. Batches are insert-only (the database checks the
 * snapshot against its gifts at commit and rejects changes); reviews are appended as ReconciliationReview evidence.
 */
final readonly class Reconciliation
{
    public const int MAX_MINOR = 100_000_000_000_000;
    public const int MAX_GIFTS = 100;

    public string $reference;
    public string $currency;
    public string $reason;
    /** @var list<string> */
    public array $giftIds;

    /** @param list<string>|null $giftIds */
    private function __construct(
        public string $id,
        ?string $reference,
        public int $expectedMinor,
        public int $actualMinor,
        ?string $currency,
        public CalendarDate $reconciledOn,
        ?string $reason,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
        ?array $giftIds,
    ) {
        $this->reference = GivingRules::text($reference, 200, 'Bank or cash batch reference');
        $this->currency = Money::currency($currency);
        if ($expectedMinor < 1 || $expectedMinor > self::MAX_MINOR || $actualMinor < 1 || $actualMinor > self::MAX_MINOR
            || null === $giftIds || [] === $giftIds || \count($giftIds) > self::MAX_GIFTS || \count(array_unique($giftIds)) !== \count($giftIds)) {
            throw GivingException::invalid('Select 1–100 distinct gifts and a positive expected batch amount.');
        }
        $reason = null === $reason ? '' : JavaString::trim($reason);
        if ($expectedMinor !== $actualMinor) {
            $reason = GivingRules::text($reason, 1000, 'Discrepancy explanation');
        }
        if (JavaString::length($reason) > 1000) {
            throw GivingException::invalid('The explanation must be at most 1000 characters.');
        }
        $this->reason = $reason;
        $this->giftIds = $giftIds;
    }

    /**
     * The request id becomes the batch id, so a retried create finds the row it wrote. $giftIds are in the order the
     * handler locked them (sorted by id).
     *
     * @param list<string> $giftIds
     */
    public static function create(
        string $id,
        ?string $reference,
        int $expectedMinor,
        int $actualMinor,
        ?string $currency,
        CalendarDate $reconciledOn,
        ?string $reason,
        string $createdBy,
        DateTimeImmutable $createdAt,
        array $giftIds,
    ): self {
        return new self($id, $reference, $expectedMinor, $actualMinor, $currency, $reconciledOn, $reason, $createdBy, $createdAt, $giftIds);
    }

    /** @param list<string> $giftIds */
    public static function reconstitute(
        string $id,
        string $reference,
        int $expectedMinor,
        int $actualMinor,
        string $currency,
        CalendarDate $reconciledOn,
        string $reason,
        string $createdBy,
        DateTimeImmutable $createdAt,
        array $giftIds,
    ): self {
        return new self($id, $reference, $expectedMinor, $actualMinor, $currency, $reconciledOn, $reason, $createdBy, $createdAt, $giftIds);
    }

    /**
     * The batch's next review, written against its current version and reversed amount. The request id becomes the review id.
     */
    public function review(string $requestId, int $currentVersion, ?string $outcome, ?string $note, int $reversedMinor, string $actorId, DateTimeImmutable $now): ReconciliationReview
    {
        return ReconciliationReview::record($requestId, $this->id, $currentVersion + 1, $outcome, $note, $reversedMinor, $actorId, $now);
    }
}
