<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;

final class Enrolment extends WorkflowRecord
{
    public function __construct(
        public readonly string $classId, public readonly string $childId, public readonly string $enrolledOn, public readonly bool $active = true,
        string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($classId);
        Input::id($childId);
        Input::date($enrolledOn);
    }

    public function deactivate(): self
    {
        return $this->changed(['active' => false]);
    }
}
