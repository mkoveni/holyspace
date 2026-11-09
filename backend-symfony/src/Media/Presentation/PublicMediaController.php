<?php

declare(strict_types=1);

namespace App\Media\Presentation;

use App\Media\Application\Query\GetPublicImage\GetPublicImageHandler;
use App\Media\Application\Query\GetPublicImage\GetPublicImageQuery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Event and community pictures by their random file id, with no session (`security.yaml`). Personal kinds answer 404
 * exactly like a file that does not exist. A stored file never changes, so it is cached for a year.
 */
final readonly class PublicMediaController
{
    public function __construct(private GetPublicImageHandler $images)
    {
    }

    #[Route('/api/public/media/{fileId}', methods: ['GET'])]
    #[Route('/api/public/media/{fileId}/{variant}', requirements: ['variant' => '[1-9][0-9]{0,3}'], methods: ['GET'])]
    public function __invoke(string $fileId, Request $request, ?string $variant = null): Response
    {
        $image = $this->images->handle(new GetPublicImageQuery($fileId, null === $variant ? null : (int) $variant));
        $response = MediaHttp::binary($image, 'public, max-age=31536000, immutable');
        $response->setEtag($fileId.(null === $variant ? '' : '@'.$variant));
        $response->isNotModified($request);

        return $response;
    }
}
