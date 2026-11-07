<?php

declare(strict_types=1);

namespace App\Shared\Application;

interface UnitOfWork
{
    /**
     * Runs the operation in one transaction: commits on return, rolls back when it throws.
     * Nested calls join the outer transaction.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function execute(callable $operation): mixed;
}
