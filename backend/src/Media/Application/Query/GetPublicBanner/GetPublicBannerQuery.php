<?php

declare(strict_types=1);

namespace App\Media\Application\Query\GetPublicBanner;

use App\Shared\Application\CQRS\Query;

final readonly class GetPublicBannerQuery implements Query
{
    public function __construct(public string $token)
    {
    }
}
