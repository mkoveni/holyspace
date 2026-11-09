<?php

declare(strict_types=1);

namespace App\Media\Application\Query\GetImage;

use App\Media\Application\Contract\ImageView;
use App\Media\Application\Contract\ImageViewReader;
use App\Media\Application\ImageAccess;
use App\Media\Domain\SlotKey;

/** The slot's version and current picture, for the staff screen that edits it. */
final readonly class GetImageHandler
{
    public function __construct(private ImageAccess $access, private ImageViewReader $reader)
    {
    }

    public function handle(GetImageQuery $query): ImageView
    {
        $key = SlotKey::of($query->kind, $query->ownerId);
        $this->access->view($query->actorId, $key);

        return $this->reader->view($key);
    }
}
