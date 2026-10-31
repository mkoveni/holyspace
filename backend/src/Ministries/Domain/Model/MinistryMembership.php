<?php

declare(strict_types=1);

namespace App\Ministries\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use DateTimeImmutable;
use InvalidArgumentException;

final class MinistryMembership extends WorkflowRecord
{
    public function __construct(
        public readonly string $ministryId,
        public readonly string $personId,
        public readonly string $joinedOn,
        public readonly string $role = 'member',
        public readonly bool $active = true,
        public readonly ?string $leftOn = null,
        public readonly array $history = [],
        string $id = '',
        string $createdAt = '',
        string $updatedAt = '',
        int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($ministryId);
        Input::id($personId);
        Input::date($joinedOn);
        if (null !== $leftOn) {
            Input::date($leftOn);
        }
        if (!in_array($role, ['member', 'leader'], true)) {
            throw new InvalidArgumentException('Invalid ministry role.');
        }
    }

    public function changeRole(string $role): self
    {
        return $this->changed(['role' => $role, 'history' => [...$this->history, ['previousRole' => $this->role, 'role' => $role, 'changedAt' => (new DateTimeImmutable())->format(DATE_ATOM)]]]);
    }

    public function leave(string $date): self
    {
        Input::date($date);
        if ($date < $this->joinedOn) {
            throw new InvalidArgumentException('Leaving date cannot precede joining.');
        }

        return $this->changed(['active' => false, 'leftOn' => $date]);
    }
}
