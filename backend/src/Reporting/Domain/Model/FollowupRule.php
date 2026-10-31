<?php

declare(strict_types=1);

namespace App\Reporting\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class FollowupRule extends WorkflowRecord
{
    public function __construct(public readonly string $name, public readonly string $segmentId, public readonly int $segmentVersion, public readonly string $assigneeId, public readonly string $ownerId, public readonly string $taskTitle, public readonly int $dueInDays = 7, public readonly bool $active = false, public readonly ?string $lastRunAt = null, public readonly ?string $reviewReason = null, string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0)
    {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        foreach ([$segmentId, $assigneeId, $ownerId] as $value) {
            Input::id($value);
        }
        Input::text($name, 200);
        Input::text($taskTitle, 200);
        if ($segmentVersion < 1 || $dueInDays < 0 || $dueInDays > 90) {
            throw new InvalidArgumentException('Invalid automation rule.');
        }
    }

    public function revise(array $data): self
    {
        return $this->changed($data + ['reviewReason' => null, 'lastRunAt' => null]);
    }

    public function ran(string $time): self
    {
        return $this->changed(['lastRunAt' => $time]);
    }

    public function pause(string $reason): self
    {
        return $this->changed(['active' => false, 'reviewReason' => $reason]);
    }
}
