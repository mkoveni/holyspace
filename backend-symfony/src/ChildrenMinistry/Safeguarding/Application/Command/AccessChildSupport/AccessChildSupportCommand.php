<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Command\AccessChildSupport;

use App\ChildrenMinistry\Safeguarding\Application\SupportRecordView;
use App\Shared\Application\Command;

/** @implements Command<SupportRecordView> */
final readonly class AccessChildSupportCommand implements Command
{
    public function __construct(public string $actorId, public string $childId, public ?string $sessionId)
    {
    }
}
