<?php

declare(strict_types=1);

namespace App\Media\Application\Query\GetPublicImage;

use App\Media\Application\Contract\ImageContent;
use App\Media\Application\Contract\ImageViewReader;
use App\Media\Application\Port\FileStore;
use App\Media\Domain\MediaException;
use App\Media\Domain\MediaFile;

/** No session: only pictures of public kinds, by their random file id. Anything else is a 404, never a 403. */
final readonly class GetPublicImageHandler
{
    public function __construct(private ImageViewReader $reader, private FileStore $files)
    {
    }

    public function handle(GetPublicImageQuery $query): ImageContent
    {
        if (1 !== preg_match(MediaFile::UUID, $query->fileId)) {
            throw MediaException::missing();
        }
        $image = $this->reader->file($query->fileId);
        if (null === $image || !$image->kind->isPublic()) {
            throw MediaException::missing();
        }
        if (null !== $query->variant && !$image->kind->policy()->hasVariant($query->variant)) {
            throw MediaException::missing();
        }
        $path = null === $query->variant ? $image->path : MediaFile::variantOf($image->path, $query->variant);

        return new ImageContent(MediaFile::MIME, $this->files->read($path));
    }
}
