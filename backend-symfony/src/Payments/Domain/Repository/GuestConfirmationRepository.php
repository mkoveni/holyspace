<?php

declare(strict_types=1);

namespace App\Payments\Domain\Repository;

use App\Payments\Domain\GuestConfirmation;

interface GuestConfirmationRepository
{
    public function find(string $id): ?GuestConfirmation;

    public function forCharge(string $chargeId): ?GuestConfirmation;

    public function insert(GuestConfirmation $confirmation): void;
}
