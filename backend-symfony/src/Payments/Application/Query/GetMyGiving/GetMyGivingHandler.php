<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetMyGiving;

use App\Giving\Application\Contract\DonorIdentity;
use App\Giving\Application\Contract\GiftFilter;
use App\Giving\Application\Contract\GivingReader;
use App\Giving\Application\Contract\OnlineFundDirectory;
use App\Payments\Application\Contract\DonorPaymentPage;
use App\Payments\Application\Contract\DonorPaymentReader;
use App\Payments\Application\Contract\PaymentGateway;
use App\Payments\Application\Contract\PaymentProvider;
use App\Payments\Domain\PaymentException;
use App\Shared\Domain\CalendarDate;

final readonly class GetMyGivingHandler
{
    public function __construct(
        private DonorIdentity $donors,
        private OnlineFundDirectory $funds,
        private PaymentGateway $gateway,
        private DonorPaymentReader $payments,
        private GivingReader $giving,
    ) {
    }

    public function handle(GetMyGivingQuery $q): MyGiving
    {
        $donor = $this->donors->current();
        if ($q->limit < 1 || $q->limit > 100 || $q->paymentOffset < 0 || $q->paymentOffset > 100_000 || $q->giftOffset < 0 || $q->giftOffset > 100_000) {
            throw PaymentException::invalid('Choose a valid giving history page.');
        }
        if (null === $donor) {
            return new MyGiving(null, [], [], new DonorPaymentPage([], 0), [], 0);
        }
        $catalog = $this->funds->funds(false);
        if (\count($catalog) > OnlineFundDirectory::CATALOGUE_LIMIT) {
            throw PaymentException::conflict('The online fund catalogue needs review. Contact the church office.');
        }
        $page = $this->giving->gifts(new GiftFilter(CalendarDate::of(1900, 1, 1), CalendarDate::of(9999, 12, 31), null, null, $donor->id, $q->limit, $q->giftOffset));

        return new MyGiving(
            $donor,
            $catalog,
            array_values(array_filter($this->gateway->providers(), static fn (PaymentProvider $p): bool => $p->available)),
            $this->payments->forDonor($donor->id, $q->limit, $q->paymentOffset),
            $page->items,
            $page->total,
        );
    }
}
