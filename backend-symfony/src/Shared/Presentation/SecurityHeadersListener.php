<?php

declare(strict_types=1);

namespace App\Shared\Presentation;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Spring Security's default response headers (Java's `SecurityConfiguration` never disables `http.headers()`), applied to
 * every `/api` response, errors, 401 and 403 included. Like Spring's writers, a header the controller already set wins:
 * the no-store cache headers are skipped when the controller chose its own `Cache-Control`, `Expires` or `Pragma`, or the
 * response is a 304, and HSTS is only sent over HTTPS. Symfony appends `private` to a `Cache-Control` without `public`.
 */
#[AsEventListener(event: 'kernel.response')]
final readonly class SecurityHeadersListener
{
    private const string PREFIX = '/api';

    public function __invoke(ResponseEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        if (self::PREFIX !== $path && !str_starts_with($path, self::PREFIX.'/')) {
            return;
        }
        $response = $event->getResponse();
        $headers = $response->headers;

        $this->setIfAbsent($response, 'X-Content-Type-Options', 'nosniff');
        $this->setIfAbsent($response, 'X-XSS-Protection', '0');
        if (!$this->hasOwnCacheHeaders($response)) {
            $headers->set('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate');
            $headers->set('Pragma', 'no-cache');
            $headers->set('Expires', '0');
        }
        if ($event->getRequest()->isSecure()) {
            $this->setIfAbsent($response, 'Strict-Transport-Security', 'max-age=31536000 ; includeSubDomains');
        }
        $this->setIfAbsent($response, 'X-Frame-Options', 'DENY');
    }

    private function setIfAbsent(Response $response, string $name, string $value): void
    {
        if (!$response->headers->has($name)) {
            $response->headers->set($name, $value);
        }
    }

    /**
     * Symfony always sends a computed `Cache-Control` (`no-cache, private` by default), and `ResponseHeaderBag` answers
     * directive queries from that computed value, so "set by the controller" is read from the explicit directives
     * `HeaderBag` keeps in its protected `$cacheControl`.
     */
    private function hasOwnCacheHeaders(Response $response): bool
    {
        $headers = $response->headers;
        if (Response::HTTP_NOT_MODIFIED === $response->getStatusCode() || $headers->has('Expires') || $headers->has('Pragma')) {
            return true;
        }

        return [] !== (fn (): array => $this->cacheControl)->call($headers);
    }
}
