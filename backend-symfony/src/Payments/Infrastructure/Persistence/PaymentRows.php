<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence;

use App\Payments\Domain\Charge;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\Refund;
use App\Payments\Domain\SettlementReview;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\Timestamps;

/** Row mappers for the payments_* evidence tables. */
final class PaymentRows
{
    public const string ORDER = 'id, donor_id, fund_id, amount_minor, provider, mode, recurring, status, checkout_id, checkout_url, checkout_fields, subscription_status, encrypted_token, token_hash, review_reason, version, request_hash, created_by, created_at, configuration_id, self_service, guest_name, guest_email_enc, guest_email_hash, guest_access_hash';
    public const string CHARGE = 'id, order_id, provider, mode, reference, amount_minor, received_on, gift_id, created_at';
    public const string REFUND = 'id, order_id, charge_id, provider, mode, reference, amount_minor, refunded_on, applied, reason, created_by, created_at';
    public const string REVIEW = 'id, order_id, charge_id, version, settlement_reference, fee_minor, bank_minor, refunded_minor, note, created_by, created_at, request_hash';

    private function __construct()
    {
    }

    /** @param array<string, mixed> $r */
    public static function order(array $r): PaymentOrder
    {
        return PaymentOrder::reconstitute(
            (string) $r['id'],
            self::text($r['donor_id']),
            (string) $r['fund_id'],
            (int) $r['amount_minor'],
            (string) $r['provider'],
            (string) $r['mode'],
            (bool) $r['recurring'],
            (string) $r['status'],
            self::text($r['checkout_id']),
            self::text($r['checkout_url']),
            self::text($r['checkout_fields']),
            (string) $r['subscription_status'],
            self::text($r['encrypted_token']),
            self::text($r['token_hash']),
            self::text($r['review_reason']),
            (int) $r['version'],
            (string) $r['request_hash'],
            (string) $r['created_by'],
            Timestamps::fromDatabase((string) $r['created_at']),
            self::text($r['configuration_id']),
            (bool) $r['self_service'],
            self::text($r['guest_name']),
            self::text($r['guest_email_enc']),
            self::text($r['guest_email_hash']),
            self::text($r['guest_access_hash'])
        );
    }

    /** @param array<string, mixed> $r */
    public static function charge(array $r): Charge
    {
        return new Charge(
            (string) $r['id'],
            (string) $r['order_id'],
            (string) $r['provider'],
            (string) $r['mode'],
            (string) $r['reference'],
            (int) $r['amount_minor'],
            CalendarDate::parse((string) $r['received_on']),
            self::text($r['gift_id']),
            Timestamps::fromDatabase((string) $r['created_at'])
        );
    }

    /** @param array<string, mixed> $r */
    public static function refund(array $r): Refund
    {
        return new Refund(
            (string) $r['id'],
            (string) $r['order_id'],
            self::text($r['charge_id']),
            (string) $r['provider'],
            (string) $r['mode'],
            (string) $r['reference'],
            (int) $r['amount_minor'],
            CalendarDate::parse((string) $r['refunded_on']),
            (bool) $r['applied'],
            (string) $r['reason'],
            self::text($r['created_by']),
            Timestamps::fromDatabase((string) $r['created_at'])
        );
    }

    /** @param array<string, mixed> $r */
    public static function review(array $r): SettlementReview
    {
        return new SettlementReview(
            (string) $r['id'],
            (string) $r['order_id'],
            (string) $r['charge_id'],
            (int) $r['version'],
            (string) $r['settlement_reference'],
            (int) $r['fee_minor'],
            (int) $r['bank_minor'],
            (int) $r['refunded_minor'],
            (string) $r['note'],
            (string) $r['created_by'],
            Timestamps::fromDatabase((string) $r['created_at']),
            (string) $r['request_hash']
        );
    }

    public static function text(mixed $value): ?string
    {
        return null === $value ? null : (string) $value;
    }
}
