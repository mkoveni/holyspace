<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\ListReminderContacts;

final readonly class ListReminderContactsQuery
{
    public function __construct(
        public string $assignmentId,
    ) {
    }
}
