<?php

declare(strict_types=1);

namespace App\Media\Application\Query\GetPublicEventBanner;

use App\Media\Application\ImageTargets;
use App\Media\Application\ReadModel\ImageReader;

final readonly class GetPublicEventBannerHandler
{
    public function __construct(private ImageReader $storage, private ImageTargets $targets)
    {
    }

    public function __invoke(GetPublicEventBannerQuery $message): array
    {
        $token = $message->token;

        return $this->storage->get('event', $this->targets->publishedEventId($token), true);
    }
}
