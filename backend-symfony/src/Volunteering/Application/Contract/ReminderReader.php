<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

interface ReminderReader
{
    public function reminder(string $id): ?ReminderRecord;
}
