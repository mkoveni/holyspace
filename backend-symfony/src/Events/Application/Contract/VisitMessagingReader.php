<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

interface VisitMessagingReader
{
    /** The account that approved automatic messages for the occurrence, or null while they are off. */
    public function approver(string $occurrenceId): ?string;

    /** @return array{registrationId: string, purpose: string, reviewHash: string}|null */
    public function message(string $deliveryId): ?array;
}
