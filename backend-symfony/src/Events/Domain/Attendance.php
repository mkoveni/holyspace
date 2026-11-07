<?php

declare(strict_types=1);

namespace App\Events\Domain;

use DateTimeImmutable;

/** A person's arrival at an occurrence. Corrections change the state; every change is kept in history. */
final readonly class Attendance
{
    public string $id;
    public string $registrationId;
    public DateTimeImmutable $arrivedAt;

    private function __construct(
        string $id,
        string $registrationId,
        DateTimeImmutable $arrivedAt,
        public string $status,
        public int $version,
    ) {
        $this->id = EventRules::id($id);
        $this->registrationId = EventRules::id($registrationId);
        $this->arrivedAt = EventRules::instant($arrivedAt);
        if (!\in_array($status, ['PRESENT', 'VOIDED'], true)) {
            throw EventException::invalid('Choose present or voided attendance.');
        }
    }

    public static function arrive(string $id, string $registrationId, DateTimeImmutable $now): self
    {
        return new self($id, $registrationId, $now, 'PRESENT', 0);
    }

    public static function reconstitute(string $id, string $registrationId, DateTimeImmutable $arrivedAt, string $status, int $version): self
    {
        return new self($id, $registrationId, $arrivedAt, $status, $version);
    }

    public function correct(string $status, int $expectedVersion): self
    {
        EventRules::version($this->version, $expectedVersion);

        return new self($this->id, $this->registrationId, $this->arrivedAt, $status, $this->version + 1);
    }
}
