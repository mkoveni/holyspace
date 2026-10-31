<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class PickupAuthorisation extends WorkflowRecord
{
    public function __construct(
        public readonly string $childId, public readonly string $collectorId, public readonly string $validUntil, public readonly bool $active = true,
        string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($childId);
        Input::id($collectorId);
        Input::date($validUntil);
        if ($childId === $collectorId) {
            throw new InvalidArgumentException('A child cannot collect themselves.');
        }
    }

    public function revoke(): self
    {
        return $this->changed(['active' => false]);
    }
}
