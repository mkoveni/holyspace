<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Application\UnitOfWork;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/** Runs the handler in one database transaction: commit when it returns, roll back when it throws. */
final readonly class TransactionMiddleware implements MiddlewareInterface
{
    public function __construct(private UnitOfWork $transactions)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        /** @var Envelope $handled */
        $handled = $this->transactions->execute(static fn (): Envelope => $stack->next()->handle($envelope, $stack));

        return $handled;
    }
}
