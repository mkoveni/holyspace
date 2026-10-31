<?php

declare(strict_types=1);

namespace App\Shared\Application\Outbox;

interface IntegrationConsumer
{
    public function name(): string;

    public function consume(string $type, array $payload): void;
}
