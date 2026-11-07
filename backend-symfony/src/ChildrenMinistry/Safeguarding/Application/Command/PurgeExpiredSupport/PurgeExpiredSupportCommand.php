<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Command\PurgeExpiredSupport;

use App\Shared\Application\Command;

/** @implements Command<int> */
final readonly class PurgeExpiredSupportCommand implements Command
{
    public function __construct(public string $actorId, public int $limit)
    {
    }
}
