<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ApproveEventNotification;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class ApproveEventNotificationCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $requestId,
        public string $registrationId,
        public string $contactId,
        public ?string $purpose,
        public string $message,
        public ?string $reviewHash,
        public string $verificationEvidence,
    ) {
    }

    /** Message and evidence stay out of logs. */
    public function __debugInfo(): array
    {
        return ['registrationId' => $this->registrationId];
    }
}
