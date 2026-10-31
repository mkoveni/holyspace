<?php

declare(strict_types=1);

namespace App\Media\Presentation\Http;

use App\Media\Application\Command\ChangePortalImage\ChangePortalImageCommand;
use App\Media\Application\Command\ChangePortalImage\ChangePortalImageHandler;
use App\Media\Application\ImageUpload;
use App\Media\Application\Query\GetPortalImage\GetPortalImageHandler;
use App\Media\Application\Query\GetPortalImage\GetPortalImageQuery;
use App\Media\Application\Query\GetPublicBanner\GetPublicBannerHandler;
use App\Media\Application\Query\GetPublicBanner\GetPublicBannerQuery;
use App\Shared\Presentation\Http\WorkflowController;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;

final class ImagesController extends WorkflowController
{
    public function __construct(
        private readonly GetPortalImageHandler $getPortalImageHandler,
        private readonly ChangePortalImageHandler $changePortalImageHandler,
        private readonly GetPublicBannerHandler $getPublicBannerHandler,
        private readonly \App\Media\Application\Query\GetPublicEventBanner\GetPublicEventBannerHandler $eventBanner,
    ) {
    }

    #[Route('/api/media/{target}/{id}', methods: ['GET'], format: 'json')]
    public function get(string $target, string $id): JsonResponse
    {
        $response = $this->result(fn () => ($this->getPortalImageHandler)(new GetPortalImageQuery($target, $id)));
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    #[Route('/api/media/{target}/{id}', methods: ['POST'], format: 'json')]
    public function upload(
        string $target,
        string $id,
        Request $request,
        #[MapUploadedFile(name: 'image')]
        UploadedFile $image,
    ): JsonResponse {
        return $this->result(fn () => ($this->changePortalImageHandler)(new ChangePortalImageCommand($target, $id, new ImageUpload($image->getPathname(), (int) $image->getSize(), $image->isValid()), $this->version($request))), 200);
    }

    #[Route('/api/media/{target}/{id}', methods: ['DELETE'], format: 'json')]
    public function remove(string $target, string $id, Request $request): JsonResponse
    {
        return $this->result(fn () => ($this->changePortalImageHandler)(new ChangePortalImageCommand($target, $id, null, $this->version($request))));
    }

    #[Route('/api/media/{target}/{id}/content', methods: ['GET'])]
    public function content(string $target, string $id): Response
    {
        return $this->binary(fn () => ($this->getPortalImageHandler)(new GetPortalImageQuery($target, $id, true)));
    }

    #[Route('/api/public/forms/{token}/banner', methods: ['GET'], requirements: ['token' => '[a-f0-9]{64}'])]
    public function banner(string $token): Response
    {
        return $this->binary(fn () => ($this->getPublicBannerHandler)(new GetPublicBannerQuery($token)));
    }

    #[Route('/api/public/forms/{token}/event-banner', methods: ['GET'], requirements: ['token' => '[a-f0-9]{64}'])]
    public function eventBanner(string $token): Response
    {
        return $this->binary(fn () => ($this->eventBanner)(new \App\Media\Application\Query\GetPublicEventBanner\GetPublicEventBannerQuery($token)));
    }

    private function binary(callable $load): Response
    {
        try {
            $image = $load()['image'];
            if (!$image) {
                throw new OutOfBoundsException('Image unavailable.');
            }

            return new Response($image['content'], 200, ['Content-Type' => $image['mime'], 'Content-Disposition' => 'inline; filename="image.png"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex, nofollow']);
        } catch (OutOfBoundsException) {
            return new JsonResponse(['message' => 'Image unavailable.'], 404, ['Cache-Control' => 'no-store']);
        } catch (InvalidArgumentException) {
            return new JsonResponse(['message' => 'Invalid image owner.'], 422);
        }
    }

    private function version(Request $request): int
    {
        $version = $request->headers->get('X-Image-Version', '');
        if (!preg_match('/^(0|[1-9][0-9]{0,8})$/D', $version)) {
            throw new InvalidArgumentException('Supply X-Image-Version from the current image metadata.');
        }

        return (int) $version;
    }
}
