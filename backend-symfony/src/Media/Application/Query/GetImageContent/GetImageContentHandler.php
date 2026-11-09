<?php

declare(strict_types=1);

namespace App\Media\Application\Query\GetImageContent;

use App\Media\Application\Contract\ImageContent;
use App\Media\Application\Contract\ImageViewReader;
use App\Media\Application\ImageAccess;
use App\Media\Application\Port\FileStore;
use App\Media\Domain\MediaException;
use App\Media\Domain\MediaFile;
use App\Media\Domain\SlotKey;

/** The authenticated route: the only way to read person, family and profile pictures. */
final readonly class GetImageContentHandler
{
    public function __construct(private ImageAccess $access, private ImageViewReader $reader, private FileStore $files)
    {
    }

    public function handle(GetImageContentQuery $query): ImageContent
    {
        $key = SlotKey::of($query->kind, $query->ownerId);
        $this->access->view($query->actorId, $key);
        $image = $this->reader->view($key)->image ?? throw MediaException::missing();
        if (null !== $query->variant && !$query->kind->policy()->hasVariant($query->variant)) {
            throw MediaException::missing();
        }
        $path = null === $query->variant ? $image->path : MediaFile::variantOf($image->path, $query->variant);

        return new ImageContent(MediaFile::MIME, $this->files->read($path));
    }
}
