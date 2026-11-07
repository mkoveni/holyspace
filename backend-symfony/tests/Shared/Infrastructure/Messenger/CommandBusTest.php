<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Messenger;

use App\Shared\Application\Command;
use App\Shared\Application\UnitOfWork;
use App\Shared\Domain\ContextException;
use App\Shared\Domain\ErrorKind;
use App\Shared\Infrastructure\Messenger\MessengerCommandBus;
use App\Shared\Infrastructure\Messenger\TransactionMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Throwable;

/** @implements Command<string> */
final readonly class Greet implements Command
{
    public function __construct(public string $name, public bool $fail = false)
    {
    }
}

final class RecordingUnitOfWork implements UnitOfWork
{
    /** @var list<string> */
    public array $log = [];

    public function execute(callable $operation): mixed
    {
        $this->log[] = 'begin';
        try {
            $result = $operation();
        } catch (Throwable $error) {
            $this->log[] = 'rollback';

            throw $error;
        }
        $this->log[] = 'commit';

        return $result;
    }
}

final class FailingDomainRule extends ContextException
{
    public function __construct()
    {
        parent::__construct(ErrorKind::Conflict, 'Nope.');
    }
}

final class CommandBusTest extends TestCase
{
    private RecordingUnitOfWork $transactions;
    private MessengerCommandBus $bus;

    protected function setUp(): void
    {
        $this->transactions = new RecordingUnitOfWork();
        $handler = function (Greet $greet): string {
            $this->transactions->log[] = 'handled';
            if ($greet->fail) {
                throw new FailingDomainRule();
            }

            return 'Hello '.$greet->name;
        };
        $this->bus = new MessengerCommandBus(new MessageBus([
            new TransactionMiddleware($this->transactions),
            new HandleMessageMiddleware(new HandlersLocator([Greet::class => [$handler]])),
        ]));
    }

    public function testReturnsTheHandlersResultAfterCommitting(): void
    {
        self::assertSame('Hello Ada', $this->bus->dispatch(new Greet('Ada')));
        self::assertSame(['begin', 'handled', 'commit'], $this->transactions->log);
    }

    public function testRollsBackAndRethrowsTheOriginalDomainFailure(): void
    {
        try {
            $this->bus->dispatch(new Greet('Ada', fail: true));
            self::fail('Expected the domain failure.');
        } catch (FailingDomainRule $error) {
            self::assertSame(ErrorKind::Conflict, $error->kind());
        }

        self::assertSame(['begin', 'handled', 'rollback'], $this->transactions->log);
    }

    public function testACommandWithoutAHandlerIsAnError(): void
    {
        $bus = new MessengerCommandBus(new MessageBus([new HandleMessageMiddleware(new HandlersLocator([]))]));

        $this->expectException(NoHandlerForMessageException::class);
        $bus->dispatch(new Greet('Ada'));
    }
}
