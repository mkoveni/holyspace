<?php

declare(strict_types=1);

namespace App\Ministries\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use DomainException;
use InvalidArgumentException;

final class GroupMeeting extends WorkflowRecord
{
    public function __construct(public readonly string $ministryId, public readonly string $occurrenceId, public readonly string $status = 'scheduled', public readonly ?string $cancellationReason = null, public readonly array $recordedPersonIds = [], string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0)
    {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($ministryId);
        Input::id($occurrenceId);
        if (!in_array($status, ['scheduled', 'cancelled'], true)) {
            throw new InvalidArgumentException('Invalid meeting status.');
        }
    }

    public function recordPerson(string $id): self
    {
        Input::id($id);

        return $this->changed(['recordedPersonIds' => array_values(array_unique([...$this->recordedPersonIds, $id]))]);
    }

    public function cancel(string $reason): self
    {
        Input::text($reason, 1000);
        if ('scheduled' !== $this->status) {
            throw new DomainException('Meeting is already cancelled.');
        }

        return $this->changed(['status' => 'cancelled', 'cancellationReason' => $reason]);
    }
}
