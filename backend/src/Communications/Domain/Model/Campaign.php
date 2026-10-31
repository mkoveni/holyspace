<?php

declare(strict_types=1);

namespace App\Communications\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class Campaign extends WorkflowRecord
{
    public function __construct(
        public readonly string $name, public readonly string $templateId, public readonly array $recipientIds, public readonly string $channel, public readonly string $subject, public readonly string $body, public readonly string $status = 'draft',
        string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::text($name, 150);
        Input::id($templateId);
        if ([] === $recipientIds || count($recipientIds) > 1000 || count(array_unique($recipientIds)) !== count($recipientIds)) {
            throw new InvalidArgumentException('Select 1 to 1000 distinct recipients.');
        }
        foreach ($recipientIds as $personId) {
            Input::id($personId);
        }
        if (!in_array($status, ['draft', 'queued', 'cancelled'], true)) {
            throw new InvalidArgumentException('Invalid campaign status.');
        }
    }

    public function changeStatus(string $status): self
    {
        return $this->changed(['status' => $status]);
    }
}
