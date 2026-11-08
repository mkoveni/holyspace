<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

/** Staff payment views (Java `PaymentReader`). */
interface PaymentReader
{
    public function list(int $limit, int $offset, bool $reviewOnly): PaymentPage;

    /** 409 when the order has more than 1000 charges or refunds. */
    public function detail(string $id): ?PaymentDetail;
}
