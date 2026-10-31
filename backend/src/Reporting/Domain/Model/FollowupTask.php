<?php

declare(strict_types=1);

namespace App\Reporting\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use DomainException;
use InvalidArgumentException;

final class FollowupTask extends WorkflowRecord
{
    public function __construct(public readonly string $personId, public readonly string $assigneeId, public readonly string $title, public readonly string $dueOn, public readonly array $requiredPermissions = ['people.read'], public readonly ?string $ruleId = null, public readonly string $status = 'open', public readonly array $history = [], string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0)
    {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($personId);
        Input::id($assigneeId);
        Input::text($title, 200);
        Input::date($dueOn);
        if (null !== $ruleId) {
            Input::id($ruleId);
        }
        if (!in_array('people.read', $requiredPermissions, true) || !in_array($status, ['open', 'in_progress', 'completed', 'cancelled'], true) || array_diff($requiredPermissions, ['people.read', 'attendance.manage', 'ministries.manage'])) {
            throw new InvalidArgumentException('Invalid follow-up task.');
        }
    }

    public function transition(string $status, string $note, string $actorId, string $time): self
    {
        if ($status === $this->status || !in_array($status, ['open', 'in_progress', 'completed', 'cancelled'], true)) {
            throw new DomainException('Choose a different valid task status.');
        }
        if (in_array($status, ['completed', 'cancelled'], true) || in_array($this->status, ['completed', 'cancelled'], true)) {
            Input::text($note, 1000);
        }
        if (mb_strlen($note) > 1000) {
            throw new InvalidArgumentException('Outcome is too long.');
        }

        return $this->changed(['status' => $status, 'history' => [...$this->history, ['action' => 'status', 'previousStatus' => $this->status, 'status' => $status, 'note' => trim($note), 'actorId' => $actorId, 'at' => $time]]]);
    }

    public function reassign(string $assigneeId, string $dueOn, string $reason, string $actorId, string $time): self
    {
        Input::text($reason, 1000);

        return $this->changed(['assigneeId' => $assigneeId, 'dueOn' => $dueOn, 'history' => [...$this->history, ['action' => 'reassigned', 'previousAssigneeId' => $this->assigneeId, 'assigneeId' => $assigneeId, 'note' => trim($reason), 'actorId' => $actorId, 'at' => $time]]]);
    }
}
