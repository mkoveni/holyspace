<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Shared;

use App\ChildrenMinistry\Shared\Application\Contract\ChildLocks;

final readonly class RecordingChildLocks implements ChildLocks
{
    public function __construct(private CallLog $log)
    {
    }

    public function lock(string $childId): void
    {
        $this->log->entries[] = 'guard:'.$childId;
    }
}
