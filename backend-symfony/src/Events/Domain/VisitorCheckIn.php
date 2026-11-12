<?php

declare(strict_types=1);

namespace App\Events\Domain;

use DateTimeImmutable;

/** What a visitor submitted at the door, and where staff review stands. Contact details are a snapshot of the form. */
final readonly class VisitorCheckIn
{
    public const string PENDING = 'PENDING';
    public const string CONFIRMED = 'CONFIRMED';
    public const string REMOVED = 'REMOVED';

    public ?string $mobile;
    public ?string $email;
    public ?string $howHeard;

    private function __construct(
        public string $registrationId,
        public string $occurrenceId,
        public string $fullName,
        ?string $mobile,
        ?string $email,
        ?string $howHeard,
        public ?int $partyAdults,
        public bool $firstTime,
        public string $consentVersion,
        public DateTimeImmutable $consentedAt,
        public string $reviewStatus,
        public DateTimeImmutable $createdAt,
    ) {
        EventRules::id($registrationId);
        EventRules::id($occurrenceId);
        $this->mobile = '' === trim((string) $mobile) ? null : EventRules::text($mobile, 40);
        $this->email = '' === trim((string) $email) ? null : EventRules::text($email, 254);
        if (null === $this->mobile && null === $this->email) {
            throw EventException::invalid('Enter a mobile number or an email address.');
        }
        $this->howHeard = '' === trim((string) $howHeard) ? null : EventRules::text($howHeard, 40);
        if (null !== $partyAdults && ($partyAdults < 1 || $partyAdults > 20)) {
            throw EventException::invalid('The number of adults must be between 1 and 20.');
        }
        EventRules::text($consentVersion, 40);
        if (!\in_array($reviewStatus, [self::PENDING, self::CONFIRMED, self::REMOVED], true)) {
            throw EventException::invalid('Invalid visitor review state.');
        }
    }

    public static function arrive(
        string $registrationId,
        string $occurrenceId,
        string $fullName,
        ?string $mobile,
        ?string $email,
        ?string $howHeard,
        ?int $partyAdults,
        bool $firstTime,
        string $consentVersion,
        DateTimeImmutable $now,
    ): self {
        return new self($registrationId, $occurrenceId, EventRules::text($fullName, 201), $mobile, $email, $howHeard, $partyAdults, $firstTime, $consentVersion, $now, self::PENDING, $now);
    }
}
