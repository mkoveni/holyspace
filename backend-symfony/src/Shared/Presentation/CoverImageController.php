<?php

declare(strict_types=1);

namespace App\Shared\Presentation;

use App\Shared\Application\CoverImage\CoverImageStore;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves an event or community cover image by its random id, without a session (`security.yaml`) so native image
 * loaders and caches can fetch it with no token in the URL or headers. Ids are never reused: a replaced image gets a
 * new id and its old bytes are deleted, so the response is cacheable for a year.
 */
final readonly class CoverImageController
{
    public function __construct(private CoverImageStore $images)
    {
    }

    #[Route('/api/public/cover-images/{id}', methods: ['GET'])]
    public function __invoke(#[PathUuid] string $id): Response
    {
        $image = $this->images->content($id);
        if (null === $image) {
            return new JsonResponse(['message' => 'Image unavailable.'], 404, ['Cache-Control' => 'no-store']);
        }

        return CoverImageHttp::binary($image, 'public, max-age=31536000, immutable');
    }
}
