<?php

declare(strict_types=1);

namespace App\Ministries\Infrastructure\Persistence;

use App\Ministries\Domain\Model\GroupMeeting;
use App\Ministries\Domain\Model\Ministry;
use App\Ministries\Domain\Model\MinistryMembership;
use App\Shared\Infrastructure\Persistence\WorkflowDocumentMapper;

final class MinistriesMapper extends WorkflowDocumentMapper
{
    protected function types(): array
    {
        return ['meetings' => GroupMeeting::class, 'ministries' => Ministry::class, 'memberships' => MinistryMembership::class];
    }
}
