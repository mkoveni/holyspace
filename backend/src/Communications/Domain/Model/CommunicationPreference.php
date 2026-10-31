<?php

declare(strict_types=1);

namespace App\Communications\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class CommunicationPreference extends WorkflowRecord
{
    public function __construct(
        public readonly string $personId, public readonly string $channel, public readonly bool $consent, public readonly string $consentNote,
        string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($personId);
        if (!in_array($channel, ['email', 'sms'], true)) {
            throw new InvalidArgumentException('Invalid channel.');
        }
        if ($consent) {
            Input::text($consentNote, 1000);
        }
    }

    public function withdraw(): self
    {
        return $this->changed(['consent' => false]);
    }
}
