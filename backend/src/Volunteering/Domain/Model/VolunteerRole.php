<?php

declare(strict_types=1);

namespace App\Volunteering\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class VolunteerRole extends WorkflowRecord
{
    public function __construct(
        public readonly string $name, public readonly bool $requiresScreening = false, public readonly array $requiredQualifications = [],
        string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::text($name, 100);
        foreach ($requiredQualifications as $qualification) {
            if (!is_string($qualification)) {
                throw new InvalidArgumentException('Qualifications must be strings.');
            } Input::text($qualification, 100);
        }
    }
}
