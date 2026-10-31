<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Uid\Uuid;

final readonly class RequestIdListener
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    #[AsEventListener(event: 'kernel.request', priority: 200)]
    public function request(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $event->getRequest()->attributes->set('_request_id', Uuid::v7()->toRfc4122());
        }
    }

    #[AsEventListener(event: 'kernel.response')]
    public function response(ResponseEvent $event): void
    {
        if ($event->isMainRequest()) {
            $event->getResponse()->headers->set('X-Request-ID', $event->getRequest()->attributes->get('_request_id', ''));
        }
    }

    #[AsEventListener(event: 'kernel.exception', priority: -10)]
    public function exception(ExceptionEvent $event): void
    {
        $error = $event->getThrowable();
        if ($error instanceof HttpExceptionInterface && $error->getStatusCode() < 500) {
            return;
        }
        $this->logger->error('Request failed.', ['requestId' => $event->getRequest()->attributes->get('_request_id'), 'route' => $event->getRequest()->attributes->get('_route'), 'exceptionClass' => $error::class]);
    }
}
