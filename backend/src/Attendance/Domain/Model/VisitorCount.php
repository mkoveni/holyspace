<?php

declare(strict_types=1);

namespace App\Attendance\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use DateTimeImmutable;
use InvalidArgumentException;

final class VisitorCount extends WorkflowRecord
{
    public function __construct(
        public readonly string $occurrenceId, public readonly int $count, public readonly array $corrections = [],
        string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($occurrenceId);
        if ($count < 0 || $count > 100000) {
            throw new InvalidArgumentException('Visitor count must be between 0 and 100,000.');
        }
    }

    public function correct(int $count, string $reason): self
    {
        Input::text($reason, 1000);

        return $this->changed(['count' => $count, 'corrections' => [...$this->corrections, ['previousCount' => $this->count, 'count' => $count, 'reason' => $reason, 'changedAt' => (new DateTimeImmutable())->format(DATE_ATOM)]]]);
    }
}
