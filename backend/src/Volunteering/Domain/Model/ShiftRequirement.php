<?php

declare(strict_types=1);

namespace App\Volunteering\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class ShiftRequirement extends WorkflowRecord
{
    public function __construct(
        public readonly string $occurrenceId, public readonly string $roleId, public readonly string $startsAt, public readonly string $endsAt, public readonly int $positions, public readonly string $status = 'open',
        string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($occurrenceId);
        Input::id($roleId);
        Input::interval($startsAt, $endsAt);
        if ($positions < 1 || $positions > 100) {
            throw new InvalidArgumentException('Positions must be between 1 and 100.');
        }
        if (!in_array($status, ['open', 'needs_review', 'cancelled'], true)) {
            throw new InvalidArgumentException('Invalid shift status.');
        }
    }

    public function changeStatus(string $status): self
    {
        return $this->changed(['status' => $status]);
    }
}
