<?php

declare(strict_types=1);

namespace App\Media\Presentation;

use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * `/api/public/cover-images/{id}` was the address of event and community covers before Media. Imported covers keep
 * their id, so the new address follows from the old one; clients that cached a `coverImageUrl` keep working.
 * An id that was never imported ends in a 404 at the target.
 */
final readonly class LegacyCoverRedirectController
{
    #[Route('/api/public/cover-images/{id}', methods: ['GET'])]
    public function __invoke(#[PathUuid] string $id): RedirectResponse
    {
        return new RedirectResponse(MediaHttp::PUBLIC_PATH.$id, 301, ['Cache-Control' => 'public, max-age=86400']);
    }
}
