<?php

declare(strict_types=1);

namespace App\Payments\Domain\Repository;

use App\Payments\Domain\MobileCheckout;
use DateTimeImmutable;

interface MobileCheckoutRepository
{
    public function insert(MobileCheckout $checkout): void;

    public function lock(string $tokenHash): ?MobileCheckout;

    public function used(string $tokenHash, DateTimeImmutable $now): void;
}
