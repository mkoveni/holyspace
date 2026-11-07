<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

use DateTimeImmutable;

interface InboxWriter
{
    /** Sets the desired read state; reading again keeps the first read time. Throws not-found for another account's notice. */
    public function read(string $account, string $notification, bool $read, DateTimeImmutable $at): void;

    /** Changes one topic preference under its optimistic version. */
    public function preference(string $account, string $topic, bool $enabled, int $expectedVersion): void;
}
