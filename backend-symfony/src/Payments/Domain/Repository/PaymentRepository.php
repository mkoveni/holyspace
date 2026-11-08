<?php

declare(strict_types=1);

namespace App\Payments\Domain\Repository;

use App\Payments\Domain\Charge;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\Refund;
use App\Payments\Domain\SettlementReview;
use DateTimeImmutable;

/**
 * Orders and their verified evidence (Java `PaymentRepository`). Every order update advances its version by one, as
 * the order guard trigger requires; `lock` holds the order row until the transaction ends.
 */
interface PaymentRepository
{
    public function find(string $id): ?PaymentOrder;

    public function lock(string $id): ?PaymentOrder;

    /** @return bool false when the id or the active monthly arrangement already exists */
    public function insert(PaymentOrder $order): bool;

    public function ready(string $id, string $checkoutId, string $url, string $fields): void;

    public function status(string $id, string $status): void;

    public function review(string $id, ?string $reason): void;

    public function subscription(string $id, string $status, ?string $encryptedToken, ?string $tokenHash): void;

    /** The order holding this provider checkout id, or (when $checkoutId is null) this subscription token hash. */
    public function lookup(string $provider, string $mode, ?string $checkoutId, ?string $tokenHash): ?string;

    public function notification(string $provider, string $mode, string $eventId): ?string;

    /** @return bool false when the provider event was already recorded */
    public function remember(string $provider, string $mode, string $eventId, string $orderId, string $fingerprint, DateTimeImmutable $now): bool;

    public function charge(string $provider, string $mode, string $reference): ?Charge;

    public function insertCharge(Charge $charge): bool;

    /** @return list<Charge> at most 1001, oldest first */
    public function charges(string $orderId): array;

    /** @return list<Charge> charges not yet posted to Giving, at most 100, oldest first */
    public function pendingCharges(string $orderId): array;

    /** @return list<Charge> the given charge of the order, or any two of its charges when $chargeId is null */
    public function matchingCharges(string $orderId, ?string $chargeId): array;

    public function posted(string $chargeId, string $giftId): void;

    public function refund(string $provider, string $mode, string $reference): ?Refund;

    public function insertRefund(Refund $refund): bool;

    /** @return list<Refund> at most 1001, oldest first */
    public function refunds(string $orderId): array;

    /** @return list<Refund> refunds not yet applied, at most 100, oldest first */
    public function pendingRefunds(string $orderId): array;

    public function applied(string $refundId, string $chargeId): void;

    public function matchRefund(string $refundId, string $chargeId): void;

    /** Sum of applied refunds of a charge. */
    public function refunded(string $chargeId): int;

    public function reviewRequest(string $requestId): ?SettlementReview;

    public function latestReview(string $chargeId): ?SettlementReview;

    public function insertReview(SettlementReview $review): void;

    public function audit(string $orderId, string $action, string $note, ?string $actorId, DateTimeImmutable $now): void;
}
