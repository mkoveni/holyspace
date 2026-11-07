<?php

declare(strict_types=1);

namespace App\Tests\Shared\Presentation;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\ContextException;
use App\Shared\Domain\ErrorKind;
use App\Shared\Presentation\ApiExceptionListener;
use App\Tests\Support\DatabaseTestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

final class ApiExceptionListenerTest extends DatabaseTestCase
{
    /** @return iterable<string, array{ErrorKind, int}> */
    public static function kinds(): iterable
    {
        yield 'invalid' => [ErrorKind::Invalid, 400];
        yield 'not found' => [ErrorKind::NotFound, 404];
        yield 'conflict' => [ErrorKind::Conflict, 409];
        yield 'unauthenticated' => [ErrorKind::Unauthenticated, 401];
        yield 'forbidden' => [ErrorKind::Forbidden, 403];
        yield 'throttled' => [ErrorKind::Throttled, 429];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('kinds')]
    public function testContextFailuresMapToStatusAndMessage(ErrorKind $kind, int $status): void
    {
        $response = $this->handle(new class ($kind, 'Something specific.') extends ContextException {
        });

        self::assertSame($status, $response->getStatusCode());
        self::assertSame('{"message":"Something specific."}', $response->getContent());
        self::assertSame(429 === $status ? '60' : null, $response->headers->get('Retry-After'));
    }

    public function testContextsCanLengthenTheRetryWindow(): void
    {
        $response = $this->handle(ChildrenException::throttled('Too many pickup attempts.'));

        self::assertSame(429, $response->getStatusCode());
        self::assertSame('900', $response->headers->get('Retry-After'));
    }

    public function testDuplicateKeyBecomesConflict(): void
    {
        $response = $this->handle($this->databaseError(function (): void {
            $this->connection->insert('roles', ['id' => Uuid::v4()->toRfc4122(), 'name' => 'dup']);
            $this->connection->insert('roles', ['id' => Uuid::v4()->toRfc4122(), 'name' => 'dup']);
        }));

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('{"message":"A duplicate or overlapping record already exists."}', $response->getContent());
    }

    public function testMissingReferenceBecomesConflict(): void
    {
        $response = $this->handle($this->databaseError(function (): void {
            $this->connection->insert('user_roles', ['user_id' => Uuid::v4()->toRfc4122(), 'role_id' => Uuid::v4()->toRfc4122()]);
        }));

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('{"message":"A referenced record is missing or has changed."}', $response->getContent());
    }

    public function testCheckViolationBecomesBadRequest(): void
    {
        $response = $this->handle($this->databaseError(function (): void {
            $this->connection->insert('roles', ['id' => Uuid::v4()->toRfc4122(), 'name' => 'NOT-LOWER']);
        }));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testExclusionViolationBecomesConflict(): void
    {
        $this->connection->executeStatement('CREATE TEMP TABLE probe (r int4range, EXCLUDE USING gist (r WITH &&))');
        $this->connection->executeStatement("INSERT INTO probe VALUES ('[1,5)')");

        $response = $this->handle($this->databaseError(function (): void {
            $this->connection->executeStatement("INSERT INTO probe VALUES ('[3,8)')");
        }));

        self::assertSame(409, $response->getStatusCode());
    }

    public function testValidationAndAccessAndRoutingFailures(): void
    {
        $validation = $this->handle(new UnprocessableEntityHttpException('x', new ValidationFailedException('v', new ConstraintViolationList())));
        self::assertSame(400, $validation->getStatusCode());
        self::assertStringContainsString('Invalid request', (string) $validation->getContent());

        self::assertSame('{"message":"Permission denied."}', $this->handle(new AccessDeniedException())->getContent());
        self::assertSame(404, $this->handle(new NotFoundHttpException())->getStatusCode());
    }

    public function testUnexpectedErrorsDoNotLeakDetails(): void
    {
        $response = $this->handle(new RuntimeException('secret connection string'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('{"message":"Unable to complete the request."}', $response->getContent());
    }

    private function databaseError(callable $operation): Throwable
    {
        try {
            $operation();
        } catch (Throwable $error) {
            return $error;
        }

        self::fail('Expected a database error');
    }

    private function handle(Throwable $error): \Symfony\Component\HttpFoundation\Response
    {
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $error);
        new ApiExceptionListener(new NullLogger())($event);

        return $event->getResponse() ?? self::fail('No response');
    }
}
