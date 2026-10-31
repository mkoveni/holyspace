<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class MinistrySession extends WorkflowRecord
{
    public function __construct(
        public readonly string $classId, public readonly string $occurrenceId, public readonly array $staffPersonIds, public readonly string $status = 'scheduled',
        string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($classId);
        Input::id($occurrenceId);
        if ([] === $staffPersonIds) {
            throw new InvalidArgumentException('At least one staff member is required.');
        }
        foreach ($staffPersonIds as $personId) {
            Input::id($personId);
        }
        if (!in_array($status, ['scheduled', 'needs_review', 'cancelled'], true)) {
            throw new InvalidArgumentException('Invalid session status.');
        }
    }

    public function confirmStaff(array $staffPersonIds): self
    {
        return $this->changed(['staffPersonIds' => $staffPersonIds, 'status' => 'scheduled']);
    }

    public function changeStatus(string $status): self
    {
        return $this->changed(['status' => $status]);
    }
}
