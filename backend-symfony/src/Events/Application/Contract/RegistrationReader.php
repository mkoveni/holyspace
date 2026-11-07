<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

interface RegistrationReader
{
    public function policy(string $occurrenceId): ?PolicyView;

    public function find(string $id): ?RegistrationView;

    /** @return list<RegistrationView> in queue order */
    public function registrations(string $occurrenceId, ?string $status, int $limit, int $offset): array;

    public function registrationCount(string $occurrenceId, ?string $status): int;

    public function counts(string $occurrenceId): RegistrationCounts;

    /**
     * @param list<string> $occurrenceIds
     *
     * @return array<string, RegistrationCounts> keyed by occurrence id; occurrences without registrations are absent
     */
    public function countsMany(array $occurrenceIds): array;
}
