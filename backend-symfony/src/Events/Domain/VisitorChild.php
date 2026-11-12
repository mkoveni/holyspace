<?php

declare(strict_types=1);

namespace App\Events\Domain;

use DateTimeImmutable;

/**
 * A child a visitor named on the form. It is only a note for Children's Ministry staff: no child record, enrolment
 * or pickup authorisation is created from a public form, so staff decide what happens next.
 */
final readonly class VisitorChild
{
    public const string PENDING = 'PENDING';
    public const string HANDED_OVER = 'HANDED_OVER';
    public const string DISMISSED = 'DISMISSED';
    public const int MAX_PER_VISIT = 8;
    public const int MAX_AGE_YEARS = 17;

    private function __construct(
        public string $id,
        public string $registrationId,
        public string $firstName,
        public int $position,
        public DateTimeImmutable $birthDate,
        public DateTimeImmutable $createdAt,
    ) {
        EventRules::id($id);
        EventRules::id($registrationId);
    }

    public static function arrive(string $id, string $registrationId, string $firstName, int $position, string $birthDate, DateTimeImmutable $now): self
    {
        return new self($id, $registrationId, EventRules::text($firstName, 100), $position, self::birthDate($birthDate, $now), $now);
    }

    private static function birthDate(string $value, DateTimeImmutable $today): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw EventException::invalid('Enter each child’s date of birth as a valid date.');
        }
        if ($date > $today || $date < $today->modify('-'.(self::MAX_AGE_YEARS + 1).' years')) {
            throw EventException::invalid('A child’s date of birth must be in the last '.(self::MAX_AGE_YEARS + 1).' years.');
        }

        return $date;
    }

    public static function allowedDecision(string $decision): string
    {
        if (!\in_array($decision, [self::HANDED_OVER, self::DISMISSED], true)) {
            throw EventException::invalid('Choose handed over or dismissed.');
        }

        return $decision;
    }
}
