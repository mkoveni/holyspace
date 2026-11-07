<?php

declare(strict_types=1);

namespace App\Giving\Domain;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/**
 * A received gift, allocated in full to 1-20 distinct funds. Posted gifts are immutable evidence (the database
 * rejects updates and deletes); a correction is a separate full Reversal.
 */
final readonly class Gift
{
    public const array METHODS = ['CASH', 'BANK', 'OTHER', 'ONLINE'];
    public const array MANUAL_METHODS = ['CASH', 'BANK', 'OTHER'];
    public const string ONLINE = 'ONLINE';

    public ?string $externalReference;
    public string $method;
    /** @var list<Allocation> */
    public array $allocations;

    /** @param list<Allocation|null>|null $allocations */
    private function __construct(
        public string $id,
        public ?string $donorId,
        public Money $money,
        public CalendarDate $receivedOn,
        ?string $method,
        ?string $externalReference,
        ?array $allocations,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
    ) {
        if ('' === $id || '' === $createdBy) {
            throw GivingException::invalid('Complete the gift details.');
        }
        if (!\in_array($method ?? '', self::METHODS, true)) {
            throw GivingException::invalid('Choose cash, bank or other. Online payments require a verified payment integration.');
        }
        $this->method = (string) $method;
        $this->externalReference = null === $externalReference || JavaString::isBlank($externalReference)
            ? null
            : GivingRules::text($externalReference, 200, 'Reference');
        $this->allocations = self::balanced($allocations, $money);
    }

    /**
     * @param list<Allocation|null>|null $allocations
     *
     * @return list<Allocation>
     */
    private static function balanced(?array $allocations, Money $money): array
    {
        if (null === $allocations || [] === $allocations || \count($allocations) > 20) {
            throw GivingException::invalid('Allocate the gift to 1–20 funds.');
        }
        $seen = [];
        $sum = 0;
        $checked = [];
        foreach ($allocations as $item) {
            if (null === $item) {
                throw GivingException::invalid('Every allocation needs a fund and an amount.');
            }
            $checked[] = $item;
        }
        foreach ($checked as $item) {
            if ('' === $item->fundId || $item->amountMinor < 1 || $item->amountMinor > $money->amountMinor || isset($seen[$item->fundId])) {
                throw GivingException::invalid('Choose each fund once with a positive allocation.');
            }
            $seen[$item->fundId] = true;
            $sum += $item->amountMinor;
        }
        if ($sum !== $money->amountMinor) {
            throw GivingException::invalid('Fund allocations must equal the full gift amount.');
        }

        return $checked;
    }

    /**
     * The request id becomes the gift id, so a retried post finds the row it wrote. A null donor is anonymous.
     *
     * @param list<Allocation|null>|null $allocations
     */
    public static function record(
        string $id,
        ?string $donorId,
        Money $money,
        ?CalendarDate $receivedOn,
        ?string $method,
        ?string $externalReference,
        ?array $allocations,
        string $createdBy,
        DateTimeImmutable $createdAt,
    ): self {
        if (null === $receivedOn) {
            throw GivingException::invalid('Complete the gift details.');
        }

        return new self($id, $donorId, $money, $receivedOn, $method, $externalReference, $allocations, $createdBy, $createdAt);
    }

    /**
     * A gift Payments verified with the provider: ZAR, method ONLINE, the whole amount to one fund. The charge id becomes
     * the gift id, so a retried post finds the row it wrote.
     */
    public static function online(
        string $chargeId,
        string $donorId,
        string $fundId,
        int $amountMinor,
        string $reference,
        CalendarDate $receivedOn,
        string $createdBy,
        DateTimeImmutable $createdAt,
    ): self {
        $money = Money::of($amountMinor, 'ZAR');

        return new self($chargeId, $donorId, $money, $receivedOn, self::ONLINE, $reference, [new Allocation($fundId, $amountMinor)], $createdBy, $createdAt);
    }

    /** @param list<Allocation> $allocations */
    public static function reconstitute(
        string $id,
        ?string $donorId,
        Money $money,
        CalendarDate $receivedOn,
        string $method,
        ?string $externalReference,
        array $allocations,
        string $createdBy,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $donorId, $money, $receivedOn, $method, $externalReference, $allocations, $createdBy, $createdAt);
    }

    /**
     * Allocations ordered by fund id, the order request hashes and fund locks use.
     *
     * @return list<Allocation>
     */
    public function allocationsByFund(): array
    {
        $sorted = $this->allocations;
        usort($sorted, static fn (Allocation $a, Allocation $b): int => strcmp($a->fundId, $b->fundId));

        return $sorted;
    }

    /** A full reversal; the request id becomes the reversal id. It may not precede the received date. */
    public function reverse(string $requestId, ?CalendarDate $date, ?string $reason, string $actorId, DateTimeImmutable $now): Reversal
    {
        if (null === $date || $date->isBefore($this->receivedOn)) {
            throw GivingException::invalid("A reversal cannot precede the gift's received date.");
        }

        return Reversal::record($requestId, $this->id, $date, $reason, $actorId, $now);
    }

    /**
     * A provider-completed refund of an online gift: at least one minor unit, at most what is still unrefunded, and not
     * before the gift was received. The refund id comes from Payments.
     */
    public function refundOnline(
        string $refundId,
        int $amountMinor,
        string $reference,
        CalendarDate $refundedOn,
        int $alreadyRefundedMinor,
        string $actorId,
        DateTimeImmutable $now,
    ): OnlineRefund {
        if (self::ONLINE !== $this->method || 1 !== \count($this->allocations) || $refundedOn->isBefore($this->receivedOn)
            || $amountMinor < 1 || $amountMinor > $this->money->amountMinor - $alreadyRefundedMinor) {
            throw GivingException::conflict('Refund must match the unrefunded online gift and its dates.');
        }

        return OnlineRefund::record($refundId, $this->id, $this->allocations[0]->fundId, $amountMinor, $reference, $refundedOn, $actorId, $now);
    }
}
