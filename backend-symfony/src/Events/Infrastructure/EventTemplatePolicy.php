<?php

declare(strict_types=1);

namespace App\Events\Infrastructure;

use App\Communications\Application\Contract\TemplatePublicationPolicy;

/** Event templates must keep every variable the notice needs, so a published template cannot silently drop the venue or time. */
final readonly class EventTemplatePolicy implements TemplatePublicationPolicy
{
    private const array KEYS = ['events.confirmation', 'events.reminder', 'events.cancellation', 'events.rescheduled'];
    private const array REQUIRED = ['name', 'event', 'startsAt', 'endsAt', 'timeZone', 'venue', 'message'];

    public function violation(string $key, string $subject, string $html, array $variables): ?string
    {
        if (!\in_array($key, self::KEYS, true)) {
            return null;
        }
        foreach (self::REQUIRED as $variable) {
            if (!\in_array($variable, $variables, true) || !str_contains($subject.$html, '{{'.$variable.'}}')) {
                return 'Event templates must retain name, event, startsAt, endsAt, timeZone, venue and message variables.';
            }
        }

        return null;
    }
}
