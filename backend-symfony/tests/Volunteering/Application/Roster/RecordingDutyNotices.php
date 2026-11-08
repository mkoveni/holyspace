<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application\Roster;

use App\Volunteering\Application\Contract\DutyNotices;

final class RecordingDutyNotices implements DutyNotices
{
    /** @var list<string> "kind:source" */
    public array $notices = [];

    public function assigned(?string $accountId, string $sourceKey): void
    {
        $this->notices[] = 'assigned:'.$sourceKey;
    }

    public function changed(?string $accountId, string $sourceKey): void
    {
        $this->notices[] = 'changed:'.$sourceKey;
    }

    public function invited(?string $accountId, string $sourceKey): void
    {
        $this->notices[] = 'invited:'.$sourceKey;
    }
}
