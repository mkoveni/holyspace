<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\ListYocoWebhooks;

final readonly class ListYocoWebhooksQuery
{
    public function __construct(public string $actorId, public string $mode)
    {
    }
}
