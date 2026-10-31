<?php

declare(strict_types=1);

namespace App\Volunteering\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;
use Symfony\Component\Clock\Clock;

final class VolunteerAssignment extends WorkflowRecord
{
    public function __construct(
        public readonly string $shiftId,
        public readonly string $personId,
        public readonly string $status = 'assigned',
        public readonly array $history = [],
        string $id = '',
        string $createdAt = '',
        string $updatedAt = '',
        int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($shiftId);
        Input::id($personId);
        if (!in_array($status, ['invited', 'assigned', 'needs_review', 'declined', 'cancelled'], true)) {
            throw new InvalidArgumentException('Invalid assignment status.');
        }
    }

    public function changeStatus(string $status): self
    {
        return $this->changed(['status' => $status, 'history' => [...$this->history, ['previousStatus' => $this->status, 'status' => $status, 'changedAt' => Clock::get()->now()->format(DATE_ATOM)]]]);
    }
}
