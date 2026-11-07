<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

use DateTimeImmutable;

interface CommunicationAudit
{
    public function record(string $actor, string $action, string $target, string $detail, DateTimeImmutable $at): void;
}
