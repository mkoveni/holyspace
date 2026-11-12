<?php

declare(strict_types=1);

namespace App\Events\Application;

use DateTimeImmutable;
use DateTimeZone;

/** Which template carries each automatic visitor message, when the follow-up goes out, and the hash that freezes it. */
final class VisitorMessageRules
{
    public const string WELCOME = 'WELCOME';
    public const string FOLLOW_UP = 'FOLLOW_UP';
    public const string APP_INVITE = 'APP_INVITE';
    public const string PERMISSION = 'events.communications.send';
    private const int FOLLOW_UP_HOUR = 9;

    private function __construct()
    {
    }

    public static function template(string $purpose): string
    {
        return 'events.visitor_'.strtolower($purpose);
    }

    public static function isVisitorTemplate(?string $key): bool
    {
        return \in_array($key, [self::template(self::WELCOME), self::template(self::FOLLOW_UP), self::template(self::APP_INVITE)], true);
    }

    public static function purposeOf(?string $key): ?string
    {
        return match ($key) {
            self::template(self::WELCOME) => self::WELCOME,
            self::template(self::FOLLOW_UP) => self::FOLLOW_UP,
            self::template(self::APP_INVITE) => self::APP_INVITE,
            default => null,
        };
    }

    /** 09:00 on the day after the occurrence ends, in the occurrence's own time zone. */
    public static function followUpAt(DateTimeImmutable $endsAt, DateTimeZone $zone): DateTimeImmutable
    {
        return $endsAt->setTimezone($zone)->modify('tomorrow')->setTime(self::FOLLOW_UP_HOUR, 0);
    }

    public static function hash(string $registrationId, string $purpose, string $recipient, string $subject, string $html, int $templateVersion): string
    {
        return Fingerprint::of($registrationId, $purpose, $recipient, $subject, $html, $templateVersion);
    }
}
