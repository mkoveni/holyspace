<?php

declare(strict_types=1);

namespace App\Volunteering\Domain\Repository;

use App\Volunteering\Domain\VolunteerReminder;

interface ReminderRepository
{
    public function insert(VolunteerReminder $reminder): void;
}
