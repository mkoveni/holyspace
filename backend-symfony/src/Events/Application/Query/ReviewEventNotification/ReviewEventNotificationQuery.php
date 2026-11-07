<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ReviewEventNotification;

final readonly class ReviewEventNotificationQuery
{
    public function __construct(public string $registrationId, public string $contactId, public ?string $purpose, public string $message)
    {
    }

    /** The message stays out of logs. */
    public function __debugInfo(): array
    {
        return ['registrationId' => $this->registrationId];
    }
}
