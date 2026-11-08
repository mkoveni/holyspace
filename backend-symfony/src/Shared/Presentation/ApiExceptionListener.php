<?php

declare(strict_types=1);

namespace App\Shared\Presentation;

use App\Shared\Domain\ContextException;
use App\Shared\Domain\ErrorKind;
use Doctrine\DBAL\Exception\DriverException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

/**
 * Translates failures into the `{"message": "..."}` bodies the frontend already expects,
 * matching the Java ApiExceptionHandler and PersistenceExceptionAdvice.
 */
#[AsEventListener(event: 'kernel.exception')]
final readonly class ApiExceptionListener
{
    private const string INVALID_REQUEST = 'Invalid request. Check the required fields and their formats.';

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $error = $event->getThrowable();

        $event->setResponse($this->respond($error));
        $event->stopPropagation();
    }

    private function respond(Throwable $error): JsonResponse
    {
        if ($error instanceof ContextException) {
            return $this->contextFailure($error);
        }
        if ($error instanceof AccessDeniedException) {
            return $this->message(403, 'Permission denied.');
        }
        if ($error instanceof HttpExceptionInterface) {
            return $this->httpFailure($error);
        }

        for ($cause = $error; null !== $cause; $cause = $cause->getPrevious()) {
            if ($cause instanceof DriverException) {
                return $this->databaseFailure($cause);
            }
        }

        $this->logger->error('Unhandled exception', ['exception' => $error]);

        return $this->message(500, 'Unable to complete the request.');
    }

    private function contextFailure(ContextException $error): JsonResponse
    {
        $status = match ($error->kind()) {
            ErrorKind::Invalid => 400,
            ErrorKind::NotFound => 404,
            ErrorKind::Conflict => 409,
            ErrorKind::Unauthenticated => 401,
            ErrorKind::Forbidden => 403,
            ErrorKind::Throttled => 429,
            ErrorKind::Unavailable => 503,
        };

        return $this->message(
            $status,
            $error->getMessage(),
            ErrorKind::Throttled === $error->kind() ? ['Retry-After' => (string) $error->retryAfterSeconds()] : [],
        );
    }

    private function httpFailure(HttpExceptionInterface $error): JsonResponse
    {
        $status = $error->getStatusCode();
        if ($status >= 500) {
            $this->logger->error('Server error', ['exception' => $error]);

            return $this->message($status, 'Unable to complete the request.');
        }
        if ($error->getPrevious() instanceof ValidationFailedException || 422 === $status) {
            return $this->message(400, self::INVALID_REQUEST);
        }

        return $this->message($status, 400 === $status ? self::INVALID_REQUEST : $this->statusText($status), $error->getHeaders());
    }

    private function databaseFailure(DriverException $error): JsonResponse
    {
        return match ($error->getSQLState()) {
            '23505', '23P01' => $this->message(409, 'A duplicate or overlapping record already exists.'),
            '23503' => $this->message(409, 'A referenced record is missing or has changed.'),
            '23514', '23502' => $this->message(400, 'The record does not meet the required validation rules.'),
            default => $this->serverFailure($error),
        };
    }

    private function serverFailure(Throwable $error): JsonResponse
    {
        $this->logger->error('Database failure', ['exception' => $error]);

        return $this->message(500, 'Unable to complete the request.');
    }

    private function statusText(int $status): string
    {
        return match ($status) {
            401 => 'Authentication is required.',
            403 => 'Permission denied.',
            404 => 'Resource not found.',
            405 => 'Method not allowed.',
            default => 'Request could not be completed.',
        };
    }

    /** @param array<string, string> $headers */
    private function message(int $status, string $message, array $headers = []): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status, $headers);
    }
}
