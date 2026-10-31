<?php

declare(strict_types=1);

namespace App\Reporting\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;

final class SavedSegment extends WorkflowRecord
{
    public function __construct(public readonly string $name, public readonly array $filters, string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0)
    {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::text($name, 200);
        SegmentCriteria::validate($filters);
    }

    public function revise(string $name, array $filters): self
    {
        return $this->changed(['name' => trim($name), 'filters' => $filters]);
    }
}
