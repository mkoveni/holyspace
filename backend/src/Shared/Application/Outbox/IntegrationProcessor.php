<?php

declare(strict_types=1);

namespace App\Shared\Application\Outbox;

interface IntegrationProcessor
{
    public function drainAll(): void;

    public function drain(int $limit = 100): int;
}
