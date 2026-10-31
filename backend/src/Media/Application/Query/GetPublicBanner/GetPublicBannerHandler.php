<?php

declare(strict_types=1);

namespace App\Media\Application\Query\GetPublicBanner;

use App\Media\Application\ImageTargets;
use App\Media\Application\ReadModel\ImageReader;

final readonly class GetPublicBannerHandler
{
    private const CONTEXTS = ['person' => 'people', 'family' => 'families', 'event' => 'events', 'form' => 'forms'];

    public function __construct(private ImageReader $storage, private ImageTargets $targets)
    {
    }

    public function __invoke(GetPublicBannerQuery $message): array
    {
        $token = $message->token;

        return $this->storage->get('form', $this->targets->publishedFormId($token), true);
    }
}
