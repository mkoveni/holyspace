<?php

declare(strict_types=1);

namespace App\Events\Application\ReadModel;

use App\Events\Domain\Model\EventRegistration;
use App\Events\Domain\Model\RegistrationSettings;

interface EventRegistrationReadStore
{
    public function settings(string $occurrenceId): RegistrationSettings;

    public function find(string $id): ?EventRegistration;

    public function forPerson(string $occurrenceId, string $personId): ?EventRegistration;

    public function pendingPayments(string $occurrenceId): array;

    public function counts(string $occurrenceId): array;

    public function page(string $occurrenceId, ?string $after, int $limit): array;

    public function nextWaitlisted(string $occurrenceId): ?EventRegistration;
}
