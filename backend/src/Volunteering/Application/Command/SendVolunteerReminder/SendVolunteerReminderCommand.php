<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\SendVolunteerReminder;

use App\Shared\Application\CQRS\Command;

final readonly class SendVolunteerReminderCommand implements Command
{
    public function __construct(
        public string $id,
        public string $channel,
        public string $reference,
    ) {
    }
}
