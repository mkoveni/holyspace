<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

interface PushWriter
{
    /** Changes one push topic preference under its optimistic version; queued jobs for the old version stop being sent. */
    public function preference(string $account, string $topic, bool $enabled, int $expectedVersion): void;

    /**
     * Queues the new notice for each of the account's active devices while the topic is opted in, pinned to the
     * current device revision and preference version. Runs inside the publishing transaction; a repeat is a no-op.
     */
    public function enqueue(string $notification, string $account, string $topic): void;
}
