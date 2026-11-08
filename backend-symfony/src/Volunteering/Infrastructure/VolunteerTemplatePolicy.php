<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure;

use App\Communications\Application\Contract\TemplatePublicationPolicy;
use App\Volunteering\Application\ReminderReview;

/** Java `VolunteerTemplatePolicy`: the shift reminder must keep every variable the review fills in. */
final readonly class VolunteerTemplatePolicy implements TemplatePublicationPolicy
{
    private const array REQUIRED = ['name', 'team', 'role', 'startsAt', 'endsAt', 'message'];

    public function violation(string $key, string $subject, string $html, array $variables): ?string
    {
        if (ReminderReview::TEMPLATE !== $key) {
            return null;
        }
        foreach (self::REQUIRED as $variable) {
            if (!\in_array($variable, $variables, true) || !str_contains($subject.$html, '{{'.$variable.'}}')) {
                return 'Volunteer reminders must retain name, team, role, startsAt, endsAt and message variables.';
            }
        }

        return null;
    }
}
