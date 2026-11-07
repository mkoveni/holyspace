<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Contract;

use App\ChildrenMinistry\Sessions\Application\SessionCloseout;
use DateTimeImmutable;

interface SessionCloseoutReader
{
    public function summarize(string $sessionId, DateTimeImmutable $now): SessionCloseout;
}
