<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/** Apply the same read/write policy to every legacy API route, including nested records. */
#[AsEventListener(event: 'kernel.controller', priority: 100)]
final readonly class ContextAccessListener
{
    public function __construct(private AuthorizationCheckerInterface $authorization)
    {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if ($request->isMethod('OPTIONS')) {
            return;
        }
        $context = match (true) {
            (bool) preg_match('#^/api/(people|relationships)(/|$)#', $request->getPathInfo()) => 'people',
            (bool) preg_match('#^/api/(families|family-overviews)(/|$)#', $request->getPathInfo()) => 'families',
            (bool) preg_match('#^/api/events(/|$)#', $request->getPathInfo()) => 'events',
            (bool) preg_match('#^/api/life-events(/|$)#', $request->getPathInfo()) => 'life_events',
            default => null,
        };
        if (null === $context) {
            return;
        }
        $permission = $context.'.'.(in_array($request->getMethod(), ['GET', 'HEAD'], true) ? 'read' : 'manage');
        if (!$this->authorization->isGranted($permission)) {
            $event->setController(static fn () => new JsonResponse(['message' => 'You do not have permission to access this area.'], 403));
        }
    }
}
