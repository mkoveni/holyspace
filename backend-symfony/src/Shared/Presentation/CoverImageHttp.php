<?php

declare(strict_types=1);

namespace App\Shared\Presentation;

use App\Shared\Application\CoverImage\CoverImageContent;
use App\Shared\Application\CoverImage\CoverImageInfo;
use App\Shared\Domain\CoverImage;
use App\Shared\Domain\CoverSlot;
use App\Shared\Domain\InvalidInputException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The cover-image HTTP contract shared by Events and Communities (the portal's `PortalImage`): `GET /api/media/{target}/{id}`
 * returns `{version, image}`, `POST` takes a multipart `image`, `DELETE` clears it, both with `X-Image-Version`.
 * Members get `coverImageUrl`, a same-origin path with a random image id and no credentials in it.
 */
final class CoverImageHttp
{
    public const string PUBLIC_PATH = '/api/public/cover-images/';

    private function __construct()
    {
    }

    public static function url(?string $imageId): ?string
    {
        return null === $imageId ? null : self::PUBLIC_PATH.$imageId;
    }

    /** @return array<string, mixed> */
    public static function metadata(CoverSlot $slot, ?CoverImageInfo $info): array
    {
        return [
            'version' => $slot->version,
            'image' => null === $info ? null : [
                'mime' => $info->contentType,
                'width' => $info->width,
                'height' => $info->height,
                'bytes' => $info->byteSize,
                'updatedAt' => Json::instant($info->createdAt),
                'url' => self::url($info->id),
            ],
        ];
    }

    /** The version the caller last read; required, as for every other write (a missing header is a 400, never 0). */
    public static function expectedVersion(Request $request): int
    {
        $version = (string) $request->headers->get('X-Image-Version', '');
        if (1 !== preg_match('/\A(0|[1-9][0-9]{0,8})\z/', $version)) {
            throw InvalidInputException::because('Supply X-Image-Version from the current image metadata.');
        }

        return (int) $version;
    }

    /** Reads the multipart `image` part; its declared type and name are ignored and the bytes decide (CoverImage). */
    public static function upload(Request $request): CoverImage
    {
        $file = $request->files->get('image');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw InvalidInputException::because('Choose a JPEG, PNG or WebP image of at most 5 MB.');
        }
        if ($file->getSize() > CoverImage::MAX_BYTES) {
            throw InvalidInputException::because('Choose an image of at most 5 MB.');
        }

        return CoverImage::fromBytes((string) file_get_contents($file->getPathname()));
    }

    /** Image bytes that can never be interpreted as a document: exact type, no sniffing, no scripts, no framing. */
    public static function binary(CoverImageContent $image, string $cacheControl): Response
    {
        return new Response($image->content, 200, [
            'Content-Type' => $image->contentType,
            'Content-Disposition' => 'inline',
            'Cache-Control' => $cacheControl,
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cross-Origin-Resource-Policy' => 'cross-origin',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
