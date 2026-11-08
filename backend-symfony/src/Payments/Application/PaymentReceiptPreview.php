<?php

declare(strict_types=1);

namespace App\Payments\Application;

use App\Communications\Application\Contract\EmailTemplates;
use App\Payments\Application\Contract\PaymentDetail;
use App\Payments\Application\Contract\PaymentReader;
use App\Payments\Domain\PaymentException;
use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonEmail;
use App\People\Application\Contract\PersonEmailDirectory;

/** Eligibility, recipients and rendering of a verified donor confirmation (Java `PaymentReceiptPreview`). */
final readonly class PaymentReceiptPreview
{
    public const string TEMPLATE = 'payments.receipt';

    public function __construct(
        private PaymentReader $payments,
        private PersonDirectory $people,
        private PersonEmailDirectory $emails,
        private EmailTemplates $templates,
    ) {
    }

    /** Only a verified live charge posted to Giving, with no refunds, can be confirmed. */
    public function eligible(string $orderId, string $chargeId): PaymentDetail
    {
        $detail = $this->payments->detail($orderId) ?? throw PaymentException::missing();
        $charge = $detail->charge($chargeId);
        if ('live' !== $detail->order->mode || null === $charge->charge->giftId || 0 !== $charge->refundedMinor || $detail->refundTouches($chargeId)) {
            throw PaymentException::conflict('Confirmations require a verified live contribution posted to Giving with no refunds.');
        }

        return $detail;
    }

    /** @return list<PersonEmail> */
    public function contacts(PaymentDetail $detail): array
    {
        if (true !== $this->people->get($detail->order->donorId)?->available) {
            throw PaymentException::conflict('The donor is unavailable for a receipt.');
        }

        return $this->emails->active($detail->order->donorId);
    }

    public function preview(PaymentDetail $detail, string $chargeId, string $contact): ReceiptPreview
    {
        $charge = $detail->charge($chargeId)->charge;
        $recipient = null;
        foreach ($this->contacts($detail) as $email) {
            if ($email->id === $contact) {
                $recipient = $email->value;
                break;
            }
        }
        if (null === $recipient) {
            throw PaymentException::conflict('Choose a current email address belonging to this donor.');
        }
        $rendered = $this->templates->render(self::TEMPLATE, [
            'name' => $detail->order->donorName,
            'amount' => 'ZAR '.self::decimal($charge->amountMinor),
            'date' => $charge->receivedOn->iso(),
            'provider' => $detail->order->provider,
            'reference' => $charge->reference,
        ]);

        return new ReceiptPreview(
            self::hash($detail, $chargeId, $contact, $recipient, $rendered->subject, $rendered->html, $rendered->version),
            $recipient,
            $rendered->subject,
            $rendered->html,
            $rendered->version,
        );
    }

    public static function hash(PaymentDetail $detail, string $chargeId, string $contact, string $recipient, string $subject, string $html, int $version): string
    {
        $charge = $detail->charge($chargeId);

        return PaymentHash::of(
            $detail->order->id,
            $chargeId,
            $charge->charge->giftId,
            $charge->charge->amountMinor,
            $charge->refundedMinor,
            $contact,
            $recipient,
            $subject,
            $html,
            $version
        );
    }

    /** `BigDecimal.valueOf(minor, 2).toPlainString()`. */
    public static function decimal(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);

        return $sign.intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', \STR_PAD_LEFT);
    }
}
