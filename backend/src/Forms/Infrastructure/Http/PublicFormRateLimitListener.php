<?php

declare(strict_types=1);

namespace App\Forms\Infrastructure\Http;

use App\Forms\Presentation\Http\PublicFormGuard;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(event: 'kernel.request', priority: 16)]
final readonly class PublicFormRateLimitListener
{
    public function __construct(private PublicFormGuard $guard)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($event->isMainRequest() && $request->isMethod('POST') && preg_match('#^/api/public/forms/[a-f0-9]{64}$#D', $request->getPathInfo())) {
            $this->guard->limit($request->getClientIp() ?? 'unknown');
        }
    }
}
