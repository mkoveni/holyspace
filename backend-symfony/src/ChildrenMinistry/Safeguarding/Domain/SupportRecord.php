<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** A child's confidential care details. The payload never appears in dumps, logs or messages. */
final readonly class SupportRecord
{
    public string $allergies;
    public string $careInstructions;
    public string $restrictedNotes;

    private function __construct(
        public string $childId,
        ?string $allergies,
        ?string $careInstructions,
        ?string $restrictedNotes,
        public CalendarDate $reviewOn,
        public CalendarDate $retainUntil,
        public int $version,
        public DateTimeImmutable $updatedAt,
    ) {
        $this->allergies = ChildrenRules::optional($allergies, 2000);
        $this->careInstructions = ChildrenRules::optional($careInstructions, 2000);
        $this->restrictedNotes = ChildrenRules::optional($restrictedNotes, 2000);
        if ($retainUntil->isBefore($reviewOn)) {
            throw ChildrenException::invalid('Retention date must not precede review date.');
        }
    }

    public static function create(string $childId, ?string $allergies, ?string $careInstructions, ?string $restrictedNotes, CalendarDate $reviewOn, CalendarDate $retainUntil, DateTimeImmutable $now): self
    {
        return new self($childId, $allergies, $careInstructions, $restrictedNotes, $reviewOn, $retainUntil, 0, $now);
    }

    public static function reconstitute(string $childId, string $allergies, string $careInstructions, string $restrictedNotes, CalendarDate $reviewOn, CalendarDate $retainUntil, int $version, DateTimeImmutable $updatedAt): self
    {
        return new self($childId, $allergies, $careInstructions, $restrictedNotes, $reviewOn, $retainUntil, $version, $updatedAt);
    }

    /** Replaces the details, checking the version the caller saw. */
    public function revise(int $expectedVersion, ?string $allergies, ?string $careInstructions, ?string $restrictedNotes, CalendarDate $reviewOn, CalendarDate $retainUntil, DateTimeImmutable $now): self
    {
        ChildrenRules::version($this->version, $expectedVersion);

        return new self($this->childId, $allergies, $careInstructions, $restrictedNotes, $reviewOn, $retainUntil, $this->version + 1, $now);
    }

    public function expiredOn(CalendarDate $today): bool
    {
        return $this->retainUntil->isBefore($today);
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['childId' => $this->childId, 'version' => $this->version];
    }
}
