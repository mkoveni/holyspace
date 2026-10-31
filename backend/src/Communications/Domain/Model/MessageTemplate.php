<?php

declare(strict_types=1);

namespace App\Communications\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class MessageTemplate extends WorkflowRecord
{
    public function __construct(
        public readonly string $name, public readonly string $channel, public readonly string $subject, public readonly string $body,
        string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::text($name, 150);
        Input::text($body, 5000);
        if (!in_array($channel, ['email', 'sms'], true)) {
            throw new InvalidArgumentException('Invalid channel.');
        }
        if ('email' === $channel) {
            Input::text($subject, 200);
        }
        if ('sms' === $channel && mb_strlen($body) > 1600) {
            throw new InvalidArgumentException('SMS message is too long.');
        }
    }
}
