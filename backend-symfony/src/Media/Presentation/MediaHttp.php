<?php

declare(strict_types=1);

namespace App\Media\Presentation;

use App\Media\Application\Contract\ImageContent;
use App\Media\Application\Contract\ImageView;
use App\Media\Domain\ImageKind;
use App\Media\Domain\ImagePolicy;
use App\Media\Domain\MediaException;
use App\Shared\Presentation\ImageUrl;
use App\Shared\Presentation\Json;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The picture HTTP contract of the portal's `PortalImage`: `GET /api/media/{target}/{id}` returns `{version, image}`,
 * `POST` takes a multipart `image`, `DELETE` clears it, both with `X-Image-Version`. The target is an `ImageKind` value.
 */
final class MediaHttp
{
    public const string PUBLIC_PATH = ImageUrl::PUBLIC_PATH;

    /** Every `ImageKind` value; the route requirement of the owner-scoped paths. */
    public const string TARGETS = 'profile|person|family|family_banner|event|event_banner|community';

    private function __construct()
    {
    }

    public static function kind(string $target): ImageKind
    {
        return ImageKind::tryFrom($target) ?? throw MediaException::missing();
    }

    /** @return array<string, mixed> */
    public static function metadata(string $target, string $ownerId, ImageView $view): array
    {
        $image = $view->image;
        if (null === $image) {
            return ['version' => $view->version, 'image' => null];
        }
        $public = $image->kind->isPublic();
        $url = static fn (?int $variant): string => $public
            ? self::PUBLIC_PATH.$image->fileId.(null === $variant ? '' : '/'.$variant)
            : '/api/media/'.$target.'/'.$ownerId.'/content'.(null === $variant ? '' : '?variant='.$variant);

        return [
            'version' => $view->version,
            'image' => [
                'mime' => $image->mime,
                'width' => $image->width,
                'height' => $image->height,
                'bytes' => $image->byteSize,
                'updatedAt' => Json::instant($image->createdAt),
                'public' => $public,
                'url' => $url(null),
                'variants' => array_map(static fn (int $width): array => ['width' => $width, 'url' => $url($width)], $image->kind->policy()->variantWidths),
            ],
        ];
    }

    /** The version the caller last read; required, as for every other write (a missing header is a 400, never 0). */
    public static function expectedVersion(Request $request): int
    {
        $version = (string) $request->headers->get('X-Image-Version', '');
        if (1 !== preg_match('/\A(0|[1-9][0-9]{0,8})\z/', $version)) {
            throw MediaException::invalid('Supply X-Image-Version from the current image metadata.');
        }

        return (int) $version;
    }

    /** Reads the multipart `image` part; its declared type and name are ignored and the bytes decide (the processor). */
    public static function upload(Request $request): string
    {
        $file = $request->files->get('image');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw MediaException::invalid('Choose a JPEG, PNG or WebP image of at most 5 MB.');
        }
        if ($file->getSize() > ImagePolicy::MAX_SOURCE_BYTES) {
            throw MediaException::invalid('Choose an image of at most 5 MB.');
        }

        return (string) file_get_contents($file->getPathname());
    }

    /** `?variant=640`: the width of a smaller copy, or null for the stored picture. */
    public static function variant(Request $request): ?int
    {
        if (!$request->query->has('variant')) {
            return null;
        }
        $value = $request->query->getString('variant');
        if (1 !== preg_match('/\A[1-9][0-9]{0,3}\z/', $value)) {
            throw MediaException::invalid('The image size is not valid.');
        }

        return (int) $value;
    }

    /** Picture bytes that can never be interpreted as a document: exact type, no sniffing, no scripts, no framing. */
    public static function binary(ImageContent $image, string $cacheControl): Response
    {
        return new Response($image->bytes, 200, [
            'Content-Type' => $image->mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => $cacheControl,
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cross-Origin-Resource-Policy' => 'cross-origin',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
