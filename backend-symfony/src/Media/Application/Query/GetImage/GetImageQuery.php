<?php

declare(strict_types=1);

namespace App\Media\Application\Query\GetImage;

use App\Media\Domain\ImageKind;

final readonly class GetImageQuery
{
    public function __construct(public string $actorId, public ImageKind $kind, public string $ownerId)
    {
    }
}
