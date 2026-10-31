<?php

declare(strict_types=1);

namespace App\Giving\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;

final class Fund extends WorkflowRecord
{
    public function __construct(
        public readonly string $name, public readonly bool $active = true,
        string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::text($name, 100);
    }
}
