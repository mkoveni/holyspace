<?php

declare(strict_types=1);

namespace App\Payments\Presentation;

use App\Giving\Application\Contract\GiftSummary;
use App\Giving\Application\Contract\OnlineFund;
use App\Payments\Application\Command\PrepareMobileCheckout\MobileHandoff;
use App\Payments\Application\Command\RetryPaymentPosting\PostingResult;
use App\Payments\Application\Contract\ChargeView;
use App\Payments\Application\Contract\ConfigurationChange;
use App\Payments\Application\Contract\DonorCharge;
use App\Payments\Application\Contract\DonorPayment;
use App\Payments\Application\Contract\DonorPaymentDetail;
use App\Payments\Application\Contract\DonorRefund;
use App\Payments\Application\Contract\HostedCheckout;
use App\Payments\Application\Contract\PaymentDetail;
use App\Payments\Application\Contract\PaymentHistory;
use App\Payments\Application\Contract\PaymentPage;
use App\Payments\Application\Contract\PaymentProvider;
use App\Payments\Application\Contract\PaymentSummary;
use App\Payments\Application\Contract\ProviderEnvironment;
use App\Payments\Application\Contract\ProviderSettingsView;
use App\Payments\Application\Contract\YocoWebhook;
use App\Payments\Application\Query\GetMyGiving\MyGiving;
use App\Payments\Application\Query\GetPaymentReceipt\ReceiptReview;
use App\Payments\Application\Query\GetPaymentSettings\PaymentSettingsOverview;
use App\Payments\Application\Query\ListYocoWebhooks\WebhookListing;
use App\Payments\Application\ReceiptPreview;
use App\Payments\Application\WebhookChange;
use App\Payments\Domain\Charge;
use App\Payments\Domain\ConfigurationCheck;
use App\Payments\Domain\Refund;
use App\Payments\Domain\SettlementReview;
use App\Payments\Domain\WebhookOperation;
use App\People\Application\Contract\PersonEmail;
use App\Shared\Presentation\Json;
use stdClass;

/** The response shapes Java's Jackson writes for the Payments records (component names, ISO instants and dates). */
final class PaymentJson
{
    private function __construct()
    {
    }

    /**
     * @param list<PaymentProvider> $providers
     *
     * @return list<array<string, mixed>>
     */
    public static function providers(array $providers): array
    {
        return array_map(static fn (PaymentProvider $p): array => ['id' => $p->id, 'name' => $p->name, 'available' => $p->available, 'mode' => $p->mode, 'recurring' => $p->recurring], $providers);
    }

    /** @return array<string, mixed> */
    public static function page(PaymentPage $page): array
    {
        return ['items' => array_map(self::summary(...), $page->items), 'total' => $page->total];
    }

    /** @return array<string, mixed> */
    public static function summary(PaymentSummary $s): array
    {
        return ['id' => $s->id, 'donorId' => $s->donorId, 'donorName' => $s->donorName, 'fundId' => $s->fundId, 'amountMinor' => $s->amountMinor,
            'provider' => $s->provider, 'mode' => $s->mode, 'recurring' => $s->recurring, 'status' => $s->status,
            'subscriptionStatus' => $s->subscriptionStatus, 'reviewReason' => $s->reviewReason, 'version' => $s->version,
            'createdAt' => Json::instant($s->createdAt), 'givingStatus' => $s->givingStatus];
    }

    /** @return array<string, mixed> */
    public static function detail(PaymentDetail $d): array
    {
        return [
            'order' => self::summary($d->order),
            'charges' => array_map(static fn (ChargeView $c): array => ['charge' => self::charge($c->charge), 'refundedMinor' => $c->refundedMinor,
                'settlement' => null === $c->settlement ? null : self::review($c->settlement), 'needsReview' => $c->needsReview,
                'givingStatus' => $c->givingStatus, 'settlementStatus' => $c->settlementStatus], $d->charges),
            'refunds' => array_map(self::refund(...), $d->refunds),
            'history' => array_map(static fn (PaymentHistory $h): array => ['action' => $h->action, 'note' => $h->note, 'actorId' => $h->actorId,
                'occurredAt' => Json::instant($h->occurredAt)], $d->history),
        ];
    }

    /** @return array<string, mixed> */
    public static function charge(Charge $c): array
    {
        return ['id' => $c->id, 'orderId' => $c->orderId, 'provider' => $c->provider, 'mode' => $c->mode, 'reference' => $c->reference,
            'amountMinor' => $c->amountMinor, 'receivedOn' => $c->receivedOn->iso(), 'giftId' => $c->giftId, 'createdAt' => Json::instant($c->createdAt)];
    }

    /** @return array<string, mixed> */
    public static function review(SettlementReview $r): array
    {
        return ['id' => $r->id, 'orderId' => $r->orderId, 'chargeId' => $r->chargeId, 'version' => $r->version,
            'settlementReference' => $r->settlementReference, 'feeMinor' => $r->feeMinor, 'bankMinor' => $r->bankMinor,
            'refundedMinor' => $r->refundedMinor, 'note' => $r->note, 'createdBy' => $r->createdBy, 'createdAt' => Json::instant($r->createdAt),
            'requestHash' => $r->requestHash];
    }

    /** @return array<string, mixed> */
    public static function refund(Refund $r): array
    {
        return ['id' => $r->id, 'orderId' => $r->orderId, 'chargeId' => $r->chargeId, 'provider' => $r->provider, 'mode' => $r->mode,
            'reference' => $r->reference, 'amountMinor' => $r->amountMinor, 'refundedOn' => $r->refundedOn->iso(), 'applied' => $r->applied,
            'reason' => $r->reason, 'createdBy' => $r->createdBy, 'createdAt' => Json::instant($r->createdAt)];
    }

    /** @return array<string, mixed> */
    public static function checkout(HostedCheckout $c): array
    {
        return ['id' => $c->id, 'url' => $c->url, 'fields' => [] === $c->fields ? new stdClass() : $c->fields];
    }

    /** @return array<string, mixed> */
    public static function check(ConfigurationCheck $c): array
    {
        return ['id' => $c->id, 'revisionId' => $c->revisionId, 'successful' => $c->successful, 'message' => $c->message,
            'checkedBy' => $c->checkedBy, 'checkedAt' => Json::instant($c->checkedAt)];
    }

    /** @return array<string, mixed> */
    public static function settings(PaymentSettingsOverview $o): array
    {
        return [
            'encryptionReady' => $o->encryptionReady,
            'providers' => array_map(static fn (ProviderSettingsView $p): array => [
                'settings' => ['provider' => $p->settings->provider, 'mode' => $p->settings->mode, 'enabled' => $p->settings->enabled,
                    'monthly' => $p->settings->monthly, 'managed' => $p->settings->managed, 'version' => $p->settings->version],
                'environments' => array_map(static fn (ProviderEnvironment $e): array => ['mode' => $e->mode, 'revisionId' => $e->revisionId,
                    'publicUrl' => $e->publicUrl, 'ipRanges' => $e->ipRanges, 'merchantIdSet' => $e->merchantIdSet, 'merchantKeySet' => $e->merchantKeySet,
                    'passphraseSet' => $e->passphraseSet, 'yocoKeySet' => $e->yocoKeySet, 'webhookSecretSet' => $e->webhookSecretSet,
                    'complete' => $e->complete, 'connection' => null === $e->connection ? null : self::check($e->connection),
                    'backendUrl' => $e->backendUrl], $p->environments),
            ], $o->providers),
            'history' => array_map(static fn (ConfigurationChange $c): array => ['provider' => $c->provider, 'mode' => $c->mode, 'version' => $c->version,
                'action' => $c->action, 'note' => $c->note, 'changedFields' => $c->changedFields, 'actorId' => $c->actorId, 'actorName' => $c->actorName,
                'occurredAt' => Json::instant($c->occurredAt)], $o->history),
        ];
    }

    /** @return array<string, mixed> */
    public static function webhooks(WebhookListing $l): array
    {
        return [
            'subscriptions' => array_map(static fn (YocoWebhook $w): array => ['id' => $w->id, 'name' => $w->name, 'url' => $w->url, 'mode' => $w->mode], $l->subscriptions),
            'pending' => array_map(static fn (WebhookOperation $o): array => ['requestId' => $o->id, 'kind' => $o->kind, 'targetId' => $o->targetId,
                'status' => $o->status, 'createdAt' => Json::instant($o->createdAt)], $l->pending),
        ];
    }

    /** @return array<string, mixed> */
    public static function change(WebhookChange $c): array
    {
        return ['requestId' => $c->requestId, 'status' => $c->status, 'message' => $c->message];
    }

    /** @return array<string, mixed> */
    public static function receipt(ReceiptReview $r): array
    {
        return [
            'contacts' => array_map(static fn (PersonEmail $e): array => ['id' => $e->id, 'value' => $e->value], $r->contacts),
            'preview' => null === $r->preview ? null : self::preview($r->preview),
            'receiptId' => $r->receiptId,
            'delivery' => null === $r->delivery ? null : ['id' => $r->delivery->id, 'status' => $r->delivery->status,
                'captured' => $r->delivery->captured, 'version' => $r->delivery->version, 'errorCode' => $r->delivery->errorCode],
        ];
    }

    /** @return array<string, mixed> */
    public static function preview(ReceiptPreview $p): array
    {
        return ['hash' => $p->hash, 'recipient' => $p->recipient, 'subject' => $p->subject, 'html' => $p->html, 'templateVersion' => $p->templateVersion];
    }

    /** @return array<string, mixed> */
    public static function posting(PostingResult $r): array
    {
        return ['complete' => $r->complete, 'message' => $r->message];
    }

    /** @return array<string, mixed> */
    public static function handoff(MobileHandoff $h): array
    {
        return ['url' => $h->url, 'expiresAt' => Json::instant($h->expiresAt)];
    }

    /** @return array<string, mixed> */
    public static function myGiving(MyGiving $g): array
    {
        return [
            'donor' => null === $g->donor ? null : ['id' => $g->donor->id, 'name' => $g->donor->name],
            'funds' => array_map(static fn (OnlineFund $f): array => ['id' => $f->id, 'name' => $f->name, 'description' => $f->description,
                'active' => $f->active, 'enabled' => $f->enabled, 'version' => $f->version], $g->funds),
            'providers' => self::providers($g->providers),
            'payments' => ['items' => array_map(self::donorPayment(...), $g->payments->items), 'total' => $g->payments->total],
            'gifts' => array_map(static fn (GiftSummary $gift): array => ['id' => $gift->id, 'amountMinor' => $gift->amountMinor, 'currency' => $gift->currency,
                'receivedOn' => $gift->receivedOn->iso(), 'method' => $gift->method, 'reversed' => $gift->reversed, 'refundedMinor' => $gift->refundedMinor], $g->gifts),
            'giftTotal' => $g->giftTotal,
        ];
    }

    /** @return array<string, mixed> */
    public static function donorDetail(DonorPaymentDetail $d): array
    {
        return [
            'order' => self::donorPayment($d->order),
            'charges' => array_map(static fn (DonorCharge $c): array => ['id' => $c->id, 'amountMinor' => $c->amountMinor, 'refundedMinor' => $c->refundedMinor,
                'receivedOn' => $c->receivedOn->iso(), 'givingStatus' => $c->givingStatus, 'confirmationStatus' => $c->confirmationStatus,
                'captured' => $c->captured, 'confirmation' => null === $c->confirmation ? null : ['subject' => $c->confirmation->subject, 'html' => $c->confirmation->html]], $d->charges),
            'refunds' => array_map(static fn (DonorRefund $r): array => ['amountMinor' => $r->amountMinor, 'refundedOn' => $r->refundedOn->iso(), 'applied' => $r->applied], $d->refunds),
        ];
    }

    /** @return array<string, mixed> */
    private static function donorPayment(DonorPayment $p): array
    {
        return ['id' => $p->id, 'fundId' => $p->fundId, 'amountMinor' => $p->amountMinor, 'provider' => $p->provider, 'mode' => $p->mode,
            'recurring' => $p->recurring, 'status' => $p->status, 'subscriptionStatus' => $p->subscriptionStatus, 'version' => $p->version,
            'createdAt' => Json::instant($p->createdAt)];
    }
}
