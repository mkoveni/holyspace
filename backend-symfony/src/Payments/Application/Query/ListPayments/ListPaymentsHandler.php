<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\ListPayments;

use App\Payments\Application\Contract\PaymentPage;
use App\Payments\Application\Contract\PaymentReader;
use App\Payments\Application\PaymentAccess;
use App\Payments\Domain\PaymentException;

final readonly class ListPaymentsHandler
{
    public function __construct(private PaymentAccess $access, private PaymentReader $reader)
    {
    }

    public function handle(ListPaymentsQuery $q): PaymentPage
    {
        $this->access->read();
        if ($q->limit < 1 || $q->limit > 100 || $q->offset < 0 || $q->offset > 100_000) {
            throw PaymentException::invalid('Choose a page of 1–100 payments.');
        }

        return $this->reader->list($q->limit, $q->offset, $q->reviewOnly);
    }
}
