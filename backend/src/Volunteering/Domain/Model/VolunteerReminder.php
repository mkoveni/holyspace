<?php

declare(strict_types=1);

namespace App\Volunteering\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;

final class VolunteerReminder extends WorkflowRecord
{
    public static function signature(array $occurrence): string
    {
        return hash('sha256', json_encode([$occurrence['startsAt'], $occurrence['endsAt'], $occurrence['status'], $occurrence['location'] ?? ''], JSON_THROW_ON_ERROR));
    }

    public function __construct(public readonly string $assignmentId, public readonly string $channel, public readonly string $reference, public readonly string $campaignId, public readonly string $signature = '', public readonly int $shiftVersion = 0, public readonly string $assignmentStatus = 'assigned', string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0)
    {
        parent::__construct($id, $createdAt, $updatedAt, $version);
    }
}
