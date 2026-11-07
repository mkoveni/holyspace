<?php

declare(strict_types=1);

namespace App\Events\Domain;

/** A local time of day without date or zone, held to microsecond precision like the TIME column. */
final readonly class TimeOfDay
{
    private function __construct(
        public int $hour,
        public int $minute,
        public int $second,
        public int $micro,
    ) {
    }

    /** Parses HH:mm, HH:mm:ss or HH:mm:ss.f (up to nine digits, truncated to microseconds). */
    public static function parse(?string $value): self
    {
        if (null === $value || 1 !== preg_match('/\A([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d)(?:\.(\d{1,9}))?)?\z/', $value, $parts)) {
            throw EventException::invalid('Choose a valid time of day.');
        }

        return new self((int) $parts[1], (int) $parts[2], (int) ($parts[3] ?? 0), (int) substr(str_pad($parts[4] ?? '', 6, '0'), 0, 6));
    }

    /** ISO form used on the wire and in the database: seconds always, fraction only when needed. */
    public function iso(): string
    {
        $text = sprintf('%02d:%02d:%02d', $this->hour, $this->minute, $this->second);

        return 0 === $this->micro ? $text : $text.'.'.rtrim(sprintf('%06d', $this->micro), '0');
    }

    /** java.time.LocalTime#toString: seconds omitted when zero, fraction in groups of three digits. */
    public function javaString(): string
    {
        $text = sprintf('%02d:%02d', $this->hour, $this->minute);
        if (0 === $this->second && 0 === $this->micro) {
            return $text;
        }
        $text .= sprintf(':%02d', $this->second);
        if (0 === $this->micro) {
            return $text;
        }

        return $text.'.'.(0 === $this->micro % 1000 ? sprintf('%03d', intdiv($this->micro, 1000)) : sprintf('%06d', $this->micro));
    }

    public function fraction(): string
    {
        return 0 === $this->micro ? '.000000' : '.'.sprintf('%06d', $this->micro);
    }
}
