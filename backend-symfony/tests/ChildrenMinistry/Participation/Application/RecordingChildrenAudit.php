<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Participation\Application;

use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use DateTimeImmutable;

final readonly class RecordingChildrenAudit implements ChildrenAudit
{
    public function __construct(private CallLog $log)
    {
    }

    public function record(string $actorId, string $action, string $targetId, string $reason, DateTimeImmutable $at): string
    {
        $this->log->entries[] = 'audit:'.$action;

        return '99999999-9999-4999-8999-999999999999';
    }
}
