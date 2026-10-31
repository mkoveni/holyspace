<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class CheckInLabel extends WorkflowRecord
{
    public function __construct(public readonly string $checkInId, public readonly string $code, public readonly int $prints = 1, public readonly string $replacementReason = '', string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0)
    {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($checkInId);
        if (!preg_match('/^[A-F0-9]{12}$/D', $code) || $prints < 1) {
            throw new InvalidArgumentException('Invalid label.');
        }
    }

    public function reprint(): self
    {
        return $this->changed(['prints' => $this->prints + 1]);
    }

    public function replace(string $reason): self
    {
        Input::text($reason, 500);

        return $this->changed(['code' => strtoupper(bin2hex(random_bytes(6))), 'prints' => $this->prints + 1, 'replacementReason' => trim($reason)]);
    }
}
