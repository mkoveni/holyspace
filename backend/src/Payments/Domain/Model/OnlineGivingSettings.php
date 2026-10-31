<?php

declare(strict_types=1);

namespace App\Payments\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class OnlineGivingSettings extends WorkflowRecord
{
    public const ID = '00000000-0000-4000-8000-000000000009';

    public function __construct(
        public readonly array $fundIds = [],
        string $id = self::ID,
        string $createdAt = '',
        string $updatedAt = '',
        int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        if (count($fundIds) > 100 || count(array_unique($fundIds)) !== count($fundIds)) {
            throw new InvalidArgumentException('Select at most 100 distinct funds.');
        }
        foreach ($fundIds as $fundId) {
            Input::id($fundId);
        }
    }

    public function configure(array $fundIds): self
    {
        return $this->changed(['fundIds' => $fundIds]);
    }
}
