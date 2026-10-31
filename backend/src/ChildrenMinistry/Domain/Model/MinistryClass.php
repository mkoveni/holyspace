<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class MinistryClass extends WorkflowRecord
{
    public function __construct(
        public readonly string $programmeId, public readonly string $name, public readonly int $minAge, public readonly int $maxAge, public readonly int $capacity,
        string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($programmeId);
        Input::text($name, 100);
        if ($minAge < 0 || $maxAge > 17 || $maxAge < $minAge || $capacity < 1 || $capacity > 200) {
            throw new InvalidArgumentException('Invalid class ages or capacity.');
        }
    }
}
