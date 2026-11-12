<?php

declare(strict_types=1);

namespace App\Events\Domain\Repository;

use DateTimeImmutable;

interface VisitorMessageRepository
{
    public function sent(string $registrationId, string $purpose): bool;

    public function record(string $registrationId, string $purpose, string $deliveryId, string $reviewHash, DateTimeImmutable $at): void;
}
