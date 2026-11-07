<?php

declare(strict_types=1);

namespace App\Shared\Application;

interface CommandBus
{
    /**
     * Runs the command's handler in one transaction. Domain failures reach the caller unchanged.
     *
     * @template TResult
     *
     * @param Command<TResult> $command
     *
     * @return TResult
     */
    public function dispatch(Command $command): mixed;
}
