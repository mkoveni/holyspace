<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

/** Donor self-service projections: personal progress only, never staff operational or bank evidence. */
interface DonorPaymentReader
{
    public function forDonor(string $donorId, int $limit, int $offset): DonorPaymentPage;

    public function ownDetail(string $donorId, string $orderId): ?DonorPaymentDetail;
}
