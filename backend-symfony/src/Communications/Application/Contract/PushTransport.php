<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

/** Sends one push and checks its receipt. Called outside any database transaction. */
interface PushTransport
{
    public function send(string $token, string $notificationId): PushTransportResult;

    public function receipt(string $ticketId): PushTransportResult;
}
