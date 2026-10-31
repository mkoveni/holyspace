<?php

declare(strict_types=1);

namespace App\Ministries\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class Ministry extends WorkflowRecord
{
    public function __construct(
        public readonly string $name,
        public readonly string $type = 'ministry',
        public readonly ?string $description = null,
        public readonly bool $active = true,
        string $id = '',
        string $createdAt = '',
        string $updatedAt = '',
        int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::text($name, 150);
        if (!in_array($type, ['ministry', 'small_group'], true)) {
            throw new InvalidArgumentException('Invalid ministry type.');
        }
        if (null !== $description && mb_strlen($description) > 5000) {
            throw new InvalidArgumentException('Description is too long.');
        }
    }

    public function archive(): self
    {
        return $this->changed(['active' => false]);
    }
}
