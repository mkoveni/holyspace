<?php

declare(strict_types=1);

namespace App\Media\Presentation;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Media\Application\Command\ChangeImage\ChangeImageCommand;
use App\Media\Application\Command\RemoveImage\RemoveImageCommand;
use App\Media\Application\Query\GetImage\GetImageHandler;
use App\Media\Application\Query\GetImage\GetImageQuery;
use App\Media\Application\Query\GetImageContent\GetImageContentHandler;
use App\Media\Application\Query\GetImageContent\GetImageContentQuery;
use App\Media\Domain\MediaException;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Staff and members manage and read pictures through the portal's image contract. Who may do what is the owning
 * context's decision (`ImageOwnership`), so there is no permission attribute here; the firewall supplies the 401.
 */
final readonly class MediaController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private GetImageHandler $images,
        private GetImageContentHandler $content,
        private RateLimiterFactoryInterface $mediaUploadLimiter,
    ) {
    }

    #[Route('/api/media/{target}/{id}', requirements: ['target' => MediaHttp::TARGETS], methods: ['GET'])]
    public function show(string $target, #[PathUuid] string $id): JsonResponse
    {
        $view = $this->images->handle(new GetImageQuery($this->account->requireAccount(), MediaHttp::kind($target), $id));

        return new JsonResponse(MediaHttp::metadata($target, $id, $view), 200, ['Cache-Control' => 'no-store']);
    }

    #[Route('/api/media/{target}/{id}/content', requirements: ['target' => MediaHttp::TARGETS], methods: ['GET'])]
    public function content(string $target, #[PathUuid] string $id, Request $request): Response
    {
        $image = $this->content->handle(new GetImageContentQuery($this->account->requireAccount(), MediaHttp::kind($target), $id, MediaHttp::variant($request)));

        return MediaHttp::binary($image, 'private, no-store');
    }

    #[Route('/api/media/{target}/{id}', requirements: ['target' => MediaHttp::TARGETS], methods: ['POST'])]
    public function upload(string $target, #[PathUuid] string $id, Request $request): JsonResponse
    {
        $actor = $this->account->requireAccount();
        $limit = $this->mediaUploadLimiter->create($actor)->consume();
        if (!$limit->isAccepted()) {
            throw MediaException::throttled($limit->getRetryAfter()->getTimestamp() - time());
        }
        $version = MediaHttp::expectedVersion($request);
        $this->commands->dispatch(new ChangeImageCommand($actor, MediaHttp::kind($target), $id, MediaHttp::upload($request), $version));

        return $this->show($target, $id);
    }

    #[Route('/api/media/{target}/{id}', requirements: ['target' => MediaHttp::TARGETS], methods: ['DELETE'])]
    public function remove(string $target, #[PathUuid] string $id, Request $request): JsonResponse
    {
        $this->commands->dispatch(new RemoveImageCommand($this->account->requireAccount(), MediaHttp::kind($target), $id, MediaHttp::expectedVersion($request)));

        return $this->show($target, $id);
    }
}
