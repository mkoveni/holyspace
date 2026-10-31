<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class ChildSupportNote extends WorkflowRecord
{
    public function __construct(public readonly string $childId, public readonly string $note, string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0)
    {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($childId);
        if (mb_strlen($note) > 2000) {
            throw new InvalidArgumentException('Support note is too long.');
        }
    }

    public function revise(string $note): self
    {
        return $this->changed(['note' => trim($note)]);
    }
}
