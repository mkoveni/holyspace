<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\IdentityAccess\Domain\Exception\RoleNotFound;
use App\IdentityAccess\Domain\Exception\UserAccountNotFound;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Exception\FeatureUnavailable;
use App\Shared\Presentation\Http\DTO\ErrorResponse;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use ValueError;

#[AsEventListener(event: 'kernel.exception')]
final class DomainExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }
        $error = $event->getThrowable();
        while ($error instanceof HandlerFailedException) {
            $wrapped = $error->getWrappedExceptions();
            if (1 !== count($wrapped)) {
                return;
            }
            $error = reset($wrapped);
        }
        $status = match (true) {
            $error instanceof FeatureUnavailable => 501,
            $error instanceof AccessDenied => 403,
            $error instanceof UserAccountNotFound, $error instanceof RoleNotFound, $error instanceof OutOfBoundsException => 404,
            $error instanceof InvalidArgumentException, $error instanceof ValueError => 422,
            $error instanceof \Doctrine\DBAL\Exception\UniqueConstraintViolationException, $error instanceof DomainException => 409,
            default => null,
        };
        if (null === $status) {
            return;
        }
        $message = $error instanceof \Doctrine\DBAL\Exception\UniqueConstraintViolationException ? 'A record with this identity already exists.' : $error->getMessage();
        $event->setResponse(new JsonResponse(new ErrorResponse($message), $status));
    }
}
