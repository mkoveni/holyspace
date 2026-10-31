<?php

declare(strict_types=1);

namespace App\Attendance\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use DateTimeImmutable;
use InvalidArgumentException;

final class AttendanceEntry extends WorkflowRecord
{
    public function __construct(
        public readonly string $occurrenceId, public readonly string $personId, public readonly string $status = 'present', public readonly array $corrections = [],
        string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($occurrenceId);
        Input::id($personId);
        if (!in_array($status, ['present', 'absent', 'excused'], true)) {
            throw new InvalidArgumentException('Invalid attendance status.');
        }
    }

    public function correct(string $status, string $reason): self
    {
        Input::text($reason, 1000);

        return $this->changed(['status' => $status, 'corrections' => [...$this->corrections, ['previousStatus' => $this->status, 'status' => $status, 'reason' => $reason, 'changedAt' => (new DateTimeImmutable())->format(DATE_ATOM)]]]);
    }
}
