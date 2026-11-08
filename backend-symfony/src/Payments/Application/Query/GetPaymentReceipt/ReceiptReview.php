<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetPaymentReceipt;

use App\Payments\Application\ReceiptPreview;
use App\People\Application\Contract\PersonEmail;

/** Before approval: the donor's contacts and an optional preview. After: the frozen message and its delivery. */
final readonly class ReceiptReview
{
    /** @param list<PersonEmail> $contacts */
    public function __construct(public array $contacts, public ?ReceiptPreview $preview, public ?string $receiptId, public ?ReceiptDelivery $delivery)
    {
    }
}
