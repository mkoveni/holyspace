<?php

declare(strict_types=1);

namespace App\Ministries\Application\Query\GetMinistries;

use App\Shared\Application\CQRS\Query;

final readonly class GetMinistriesQuery implements Query
{
    public function __construct()
    {
    }
}
