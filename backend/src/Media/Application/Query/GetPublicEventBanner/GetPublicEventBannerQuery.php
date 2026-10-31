<?php

declare(strict_types=1);

namespace App\Media\Application\Query\GetPublicEventBanner;

use App\Shared\Application\CQRS\Query;

final readonly class GetPublicEventBannerQuery implements Query
{
    public function __construct(public string $token)
    {
    }
}
